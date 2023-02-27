<?php

namespace MediaWiki\Extension\MultiMail\Mail;

use CentralIdLookup;
use IContextSource;
use InvalidArgumentException;
use LogicException;
use MailAddress;
use MediaWiki\Extension\MultiMail\Hook\EmailHandler;
use MediaWiki\Extension\MultiMail\Hook\HookRunner;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Mail\IEmailer;
use MediaWiki\MainConfigNames;
use Sanitizer;
use Status;
use Title;
use TitleFactory;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use function strlen;
use function wfTimestampNow;
use const DB_PRIMARY;
use const DB_REPLICA;

class MailManager {
	private ILoadBalancer $lb;

	private CentralIdLookup $centralIdLookup;

	private IEmailer $emailer;

	private HookRunner $hookRunner;

	/** @var false|string */
	private $mailDb;

	private bool $emailAuthentication;

	private int $userEmailConfirmationTokenExpiry;

	private Title $emailConfirmTitle;

	private Title $emailUndoTitle;

	/**
	 * @param ILoadBalancer $lb
	 * @param CentralIdLookup $centralIdLookup
	 * @param IEmailer $emailer
	 * @param TitleFactory $titleFactory
	 * @param HookContainer $hookContainer
	 * @param false|string $mailDb
	 * @param bool $emailAuthentication
	 * @param int $userEmailConfirmationTokenExpiry
	 */
	public function __construct(
		ILoadBalancer $lb,
		CentralIdLookup $centralIdLookup,
		IEmailer $emailer,
		TitleFactory $titleFactory,
		HookContainer $hookContainer,
		$mailDb,
		bool $emailAuthentication,
		int $userEmailConfirmationTokenExpiry
	) {
		$this->lb = $lb;
		$this->centralIdLookup = $centralIdLookup;
		$this->emailer = $emailer;
		$this->hookRunner = new HookRunner( $hookContainer );
		$this->mailDb = $mailDb;
		$this->emailAuthentication = $emailAuthentication;
		$this->userEmailConfirmationTokenExpiry = $userEmailConfirmationTokenExpiry;

		$title = $titleFactory->makeTitle( NS_SPECIAL, 'EmailAddresses' );

		$this->emailConfirmTitle = $title->getSubpage( 'confirm' );
		$this->emailUndoTitle = $title->getSubpage( 'primary' );
	}

	/**
	 * Get a connection to the database with the user_secondary_emails table.
	 *
	 * @param int $i
	 * @return IDatabase
	 */
	public function getMailDb( int $i ): IDatabase {
		return $this->lb->getConnection( $i, [], $this->mailDb );
	}

	/**
	 * Validate if the given email address is valid.
	 * This takes null to allow it to serve as HTMLForm callback.
	 *
	 * @param string|null $address
	 * @return Status
	 */
	public function validateEmailAddress( ?string $address ): Status {
		if ( !$address || strlen( $address ) > 255 || !Sanitizer::validateEmail( $address ) ) {
			return Status::newFatal( 'multimail-invalid-email' );
		} else {
			return Status::newGood();
		}
	}

	/**
	 * Send a confirmation email to the provided secondary email.
	 * This does not check if the site has enabled email authentication.
	 *
	 * @param SecondaryEmail $email Email to send a confirmation message to
	 * @param IContextSource $userContext User context used for localizing in the users language
	 * @return Status
	 */
	public function sendConfirmationMail( SecondaryEmail $email, IContextSource $userContext ): Status {
		list( $token, $expiration, $hashedToken ) = $email->generateNewConfirmationToken();

		$dbw = $this->getMailDb( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( 'user_secondary_email' )
			->set( [
				'use_email_token' => $hashedToken,
				'use_email_token_expires' => $dbw->timestamp( $expiration )
			] )
			->where( [ 'use_id' => $email->getId() ] )
			->caller( __METHOD__ )
			->execute();

		if ( $dbw->affectedRows() === 0 ) {
			return Status::newFatal( 'multimail-manager-db-confirmation-code-add-fail' );
		}

		$lang = $userContext->getLanguage();
		$user = $userContext->getUser();

		$status = $this->emailer->send(
			MailAddress::newFromUser( $email ),
			new MailAddress(
				$userContext->getConfig()->get( MainConfigNames::PasswordSender ),
				$userContext->msg( 'emailsender' )->inContentLanguage()->text()
			),
			$userContext->msg( 'multimail-confirmationmail-secondary_subject' )->text(),
			$userContext->msg(
				'multimail-confirmationmail-secondary_body',
				$userContext->getRequest()->getIP(),
				$user->getName(),
				$this->emailConfirmTitle
					->getSubpage( (string)$email->getId() )
					->getSubpage( $token )
					->getCanonicalURL(),
				$lang->userTimeAndDate( $expiration, $user ),
				$lang->userDate( $expiration, $user ),
				$lang->userTime( $expiration, $user )
			)->text()
		);

		return Status::wrap( $status );
	}

	/**
	 * Find a secondary email address by id.
	 *
	 * @param int $id Id of the email address in the database
	 * @param User $user User this email address should belong to
	 * @return SecondaryEmail|null Null if not found
	 * @throws InvalidArgumentException when $id is negative
	 */
	public function getEmailFromId( int $id, User $user ): ?SecondaryEmail {
		if ( $id < 0 ) {
			throw new InvalidArgumentException( '$id must not be negative!' );
		}

		return $this->selectEmail( $user, [ 'use_id' => $id ] );
	}

	/**
	 * Find a secondary email address by email address.
	 *
	 * @param string $address Email address
	 * @param User $user User this email address should belong to
	 * @return SecondaryEmail|null Null if not found or $address is not a valid email address
	 */
	public function getEmailFromAddress( string $address, User $user ): ?SecondaryEmail {
		if ( !Sanitizer::validateEmail( $address ) ) {
			return null;
		}

		return $this->selectEmail( $user, [ 'use_email' => $address ] );
	}

	/**
	 * Make the provided email address the primary email address.
	 * This will send a message to the old and new email address about the change.
	 *
	 * @param SecondaryEmail $email Email address to make primary
	 * @param IContextSource $userContext User context used for localizing in the users language
	 * @return Status
	 */
	public function makePrimary( SecondaryEmail $email, IContextSource $userContext ): Status {
		$user = $userContext->getUser();

		if ( !$email->getUser()->equals( $userContext->getUser() ) ) {
			throw new InvalidArgumentException( 'Given secondary email does not belong to the given user!' );
		} elseif ( !$user->isEmailConfirmed() ) {
			return Status::newFatal( 'multimail-manager-primary-email-not-confirmed' );
		} elseif ( !$email->isEmailConfirmed() ) {
			return Status::newFatal( 'multimail-manager-secondary-email-not-confirmed' );
		}

		// Check after isEmailConfirmed to catch weird cases where emails are confirmed but not valid email addresses.
		if ( !Sanitizer::validateEmail( $user->getEmail() ) ) {
			throw new InvalidArgumentException( 'Current email address is not valid!' );
		}

		$centralId = $this->centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );

		if ( !$centralId ) {
			throw new InvalidArgumentException( 'Cannot add secondary email for unattached user!' );
		}

		$dbw = $this->getMailDb( DB_PRIMARY );

		$existingEntry = $dbw->newSelectQueryBuilder()
			->select( 'use_id' )
			->from( 'user_secondary_email' )
			->where( [
				'use_cuid' => $centralId,
				'use_email' => $user->getEmail()
			] )
			->caller( __METHOD__ )
			->fetchField();

		$set = [
			'use_email' => $user->getEmail(),
			'use_email_authenticated' => $dbw->timestampOrNull( $user->getEmailAuthenticationTimestamp() )
		];

		if ( $existingEntry ) {
			$dbw->newUpdateQueryBuilder()
				->update( 'user_secondary_email' )
				->set( $set )
				->where( [ 'use_id' => $existingEntry ] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$dbw->insert(
				'user_secondary_email',
				[ 'use_cuid' => $centralId ] + $set,
				__METHOD__
			);

			$existingEntry = $dbw->insertId();
		}

		if ( $dbw->affectedRows() === 0 ) {
			return Status::newFatal( 'multimail-manager-db-save-old-primary-fail' );
		}

		$oldPrimary = MailAddress::newFromUser( $user );

		$user->setEmail( $email->getEmail() );
		$user->setEmailAuthenticationTimestamp( $email->getEmailAuthenticationTimestamp() );

		LoggerFactory::getInstance( 'authentication' )->info(
			'Changing primary email address for {user} from {oldemail} to {newemail}', [
				'user' => $user->getName(),
				'oldemail' => $oldPrimary->address,
				'newemail' => $user->getEmail(),
			]
		);

		// MultiMails shouldn't handle the next hook calls, as we're emitting them.
		EmailHandler::setCalledFromMultiMail();
		$this->hookRunner->onPrefsEmailAudit( $user, $oldPrimary->address, $user->getEmail() );

		$user->saveSettings();

		$timestamp = wfTimestampNow();
		$lang = $userContext->getLanguage();

		$subject = $userContext->msg( 'multimail-primary-swapped_subject' )->text();
		$body = $userContext->msg(
			'multimail-primary-swapped_body',
			$user->getName(),
			$oldPrimary->address,
			$user->getEmail(),
			$lang->userTimeAndDate( $timestamp, $user ),
			$userContext->getRequest()->getIP(),
			$this->emailUndoTitle->getSubpage( $existingEntry )->getCanonicalURL(),
			$lang->userDate( $timestamp, $user ),
			$lang->userTime( $timestamp, $user )
		)->text();
		$sender = new MailAddress(
			$userContext->getConfig()->get( MainConfigNames::PasswordSender ),
			$userContext->msg( 'emailsender' )->inContentLanguage()->text()
		);

		return Status::wrap(
			$this->emailer->send( $oldPrimary, $sender, $subject, $body )
		)->merge(
			$this->emailer->send( MailAddress::newFromUser( $user ), $sender, $subject, $body )
		);
	}

	/**
	 * Add a new secondary email address.
	 * It will be unconfirmed, and no confirmation mail will be sent.
	 *
	 * @param string $address Email address
	 * @param User $user User to register the email address for
	 * @return Status Containing the SecondaryEmail instance if Good
	 */
	public function addEmail( string $address, User $user ): Status {
		$status = $this->validateEmailAddress( $address );

		if ( !$status->isGood() ) {
			return $status;
		} elseif ( $user->getEmail() === $address || $this->getEmailFromAddress( $address, $user ) ) {
			return Status::newFatal( 'multimail-manager-address-already-exists' );
		}

		$dbw = $this->getMailDb( DB_PRIMARY );

		$dbw->insert(
			'user_secondary_email',
			[
				'use_cuid' => $this->centralIdLookup->centralIdFromLocalUser( $user ),
				'use_email' => $address
			],
			__METHOD__
		);

		if ( $dbw->affectedRows() === 0 ) {
			return Status::newFatal( 'multimail-manager-db-add-new-secondary-fail' );
		}

		return Status::newGood(
			new SecondaryEmail(
				$user,
				(object)[
					'use_id' => $dbw->insertId(),
					'use_email' => $address,
					'use_email_authenticated' => null,
					'use_email_token' => null,
					'use_email_token_expires' => null
				],
				$this->emailAuthentication,
				$this->userEmailConfirmationTokenExpiry
			)
		);
	}

	/**
	 * Add a secondary email address, and when enabled, send a confirmation email.
	 *
	 * @param string $address Email address
	 * @param IContextSource $userContext User context used for localizing in the users language
	 * @return Status Containing the SecondaryEmail instance if Good
	 * @throws InvalidArgumentException When the email address is invalid
	 */
	public function addEmailAndSendConfirmationEmail( string $address, IContextSource $userContext ): Status {
		$status = $this->addEmail( $address, $userContext->getUser() );

		if ( !$this->emailAuthentication || !$status->isGood() ) {
			return $status;
		}

		return $this->sendConfirmationMail( $status->getValue(), $userContext );
	}

	/**
	 * Confirm the given email address with the given token.
	 *
	 * @param int $id Email address id
	 * @param string $token Token
	 * @param User $user User to which the email address belongs
	 * @return bool
	 * @throws InvalidArgumentException when $id is negative, $token not 32 characters, or $user is unattached
	 */
	public function confirm( int $id, string $token, User $user ): bool {
		if ( !$this->emailAuthentication ) {
			throw new LogicException(
				'This method should not have been called, $wgEmailAuthentication is false!'
			);
		} elseif ( $id < 0 ) {
			throw new InvalidArgumentException( '$id must not be negative!' );
		} elseif ( strlen( $token ) !== 32 ) {
			throw new InvalidArgumentException( 'Invalid token format!' );
		}

		$centralId = $this->centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );

		if ( !$centralId ) {
			throw new InvalidArgumentException( 'Cannot verify secondary email for unattached user!' );
		}

		$dbw = $this->getMailDb( DB_PRIMARY );
		$row = $dbw->newSelectQueryBuilder()
			->select( [
				'use_id',
				'use_email',
				'use_email_authenticated',
				'use_email_token',
				'use_email_token_expires'
			] )
			->from( 'user_secondary_email' )
			->where( [
				'use_id' => $id,
				'use_cuid' => $centralId,
				'use_email_authenticated' => null,
				$dbw->buildComparison( '>', [ 'use_email_token_expires' => $dbw->timestamp() ] ),
				'use_email_token' => md5( $token )
			] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			return false;
		}

		$email = new SecondaryEmail( $user, $row, $this->emailAuthentication, $this->userEmailConfirmationTokenExpiry );

		return $this->updateAuthenticationStatus( $email, wfTimestampNow() );
	}

	/**
	 * Update the authentication status of the given secondary email.
	 *
	 * @param SecondaryEmail $email
	 * @param string|null $newValue New value. Null removes the authentication status
	 * @return bool
	 */
	public function updateAuthenticationStatus( SecondaryEmail $email, ?string $newValue ): bool {
		$dbw = $this->getMailDb( DB_PRIMARY );
		$dbw->newUpdateQueryBuilder()
			->update( 'user_secondary_email' )
			->set( [ 'use_email_authenticated' => $dbw->timestampOrNull( $newValue ) ] )
			->where( [ 'use_id' => $email->getId() ] )
			->caller( __METHOD__ )
			->execute();

		return $dbw->affectedRows() > 0;
	}

	/**
	 * Delete the given secondary email.
	 *
	 * @param SecondaryEmail $email
	 * @return Status
	 */
	public function delete( SecondaryEmail $email ): Status {
		$centralId = $this->centralIdLookup->centralIdFromLocalUser( $email->getUser(), CentralIdLookup::AUDIENCE_RAW );

		if ( !$centralId ) {
			throw new InvalidArgumentException( 'Cannot remove secondary email for unattached user!' );
		}

		$dbw = $this->getMailDb( DB_PRIMARY );

		$dbw->delete(
			'user_secondary_email',
			[
				'use_id' => $email->getId(),
				'use_cuid' => $centralId
			],
			__METHOD__
		);

		if ( $dbw->affectedRows() === 0 ) {
			return Status::newFatal( 'multimail-manager-no-such-email-in-db' );
		} else {
			return Status::newGood();
		}
	}

	/**
	 * Select a secondary email address from the database with the given conditions.
	 *
	 * @param User $user
	 * @param array $condition Query conditions (a la $conds)
	 * @return SecondaryEmail|null
	 * @throws InvalidArgumentException when $user is unattached
	 */
	private function selectEmail( User $user, array $condition ): ?SecondaryEmail {
		$centralId = $this->centralIdLookup->centralIdFromLocalUser( $user, CentralIdLookup::AUDIENCE_RAW );

		if ( !$centralId ) {
			throw new InvalidArgumentException( 'Cannot select secondary email for unattached user!' );
		}

		$queryBuilder = $this->getMailDb( DB_REPLICA )->newSelectQueryBuilder()
			->select( [
				'use_id',
				'use_email',
				'use_email_authenticated',
				'use_email_token',
				'use_email_token_expires'
			] )
			->from( 'user_secondary_email' )
			->where( [ 'use_cuid' => $centralId ] + $condition )
			->caller( __METHOD__ );

		$row = $queryBuilder->fetchRow();

		if ( !$row ) {
			return null;
		}

		return new SecondaryEmail(
			$user,
			$row,
			$this->emailAuthentication,
			$this->userEmailConfirmationTokenExpiry
		);
	}
}
