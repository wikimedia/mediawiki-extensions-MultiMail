<?php

namespace MediaWiki\Extension\MultiMail\Specials;

use ErrorPageError;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Extension\MultiMail\Specials\Views\AddEmailView;
use MediaWiki\Extension\MultiMail\Specials\Views\ChangePrimaryView;
use MediaWiki\Extension\MultiMail\Specials\Views\ConfirmEmailView;
use MediaWiki\Extension\MultiMail\Specials\Views\DeleteEmailView;
use MediaWiki\Extension\MultiMail\Specials\Views\EmailsView;
use MediaWiki\Extension\MultiMail\Specials\Views\RequestConfirmationCodeView;
use MediaWiki\MainConfigNames;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\CentralId\CentralIdLookup;
use UserNotLoggedIn;
use function str_starts_with;
use function substr_count;

class SpecialEmailAddresses extends SpecialPage {
	private MailManager $mailManager;

	private CentralIdLookup $centralIdLookup;

	/**
	 * @param AuthManager $authManager
	 * @param MailManager $mailManager
	 * @param CentralIdLookup $centralIdLookup
	 */
	public function __construct(
		AuthManager $authManager,
		MailManager $mailManager,
		CentralIdLookup $centralIdLookup
	) {
		parent::__construct( 'EmailAddresses', 'multimail' );

		$this->setAuthManager( $authManager );
		$this->mailManager = $mailManager;
		$this->centralIdLookup = $centralIdLookup;
	}

	/**
	 * @inheritDoc
	 *
	 * @param string|null $subPage
	 * @throws ErrorPageError
	 * @throws UserNotLoggedIn
	 */
	public function execute( $subPage ): void {
		$this->setHeaders();
		$this->checkPermissions();

		$out = $this->getOutput();
		$out->disallowUserJs();
		$out->enableOOUI();

		$this->requireNamedUser( 'multimail-needlogin' );

		if ( !$this->getUser()->getEmail() ) {
			throw new ErrorPageError(
				'multimail-no-primary-email',
				'multimail-no-primary-body'
			);
		} elseif ( !$this->getConfig()->get( MainConfigNames::EnableEmail ) ) {
			throw new ErrorPageError(
				'multimail-email-disabled',
				'multimail-email-disabled-body'
			);
		}

		if ( str_starts_with( $subPage, 'add' ) ) {
			$view = new AddEmailView( $this, $this->mailManager );
		} elseif ( str_starts_with( $subPage, 'primary/' ) ) {
			$view = new ChangePrimaryView( $this, $this->mailManager );
		} elseif ( str_starts_with( $subPage, 'delete/' ) ) {
			$view = new DeleteEmailView( $this, $this->mailManager );
		} elseif (
			str_starts_with( $subPage, 'confirm/' ) &&
			$this->getConfig()->get( MainConfigNames::EmailAuthentication )
		) {
			if ( substr_count( $subPage, '/' ) === 2 ) {
				$view = new ConfirmEmailView( $this, $this->mailManager );
			} else {
				$view = new RequestConfirmationCodeView( $this, $this->mailManager );
			}
		} else {
			( new EmailsView( $this, $this->mailManager, $this->centralIdLookup ) )
				->show( $subPage );

			return;
		}

		if ( !$this->getAuthManager()->allowsPropertyChange( 'emailaddress' ) ) {
			throw new ErrorPageError( 'changeemail', 'cannotchangeemail' );
		}

		$this->requireNamedUser( 'changeemail-no-info' );

		if ( !$this->checkLoginSecurityLevel( $this->getLoginSecurityLevel() ) ) {
			return;
		}

		$this->checkReadOnly();

		$view->show( $subPage );
	}

	/**
	 * @inheritDoc
	 *
	 * @codeCoverageIgnore
	 *
	 * Only exists to allow @see View to access it.
	 */
	public function outputHeader( $summaryMessageKey = '' ): void {
		parent::outputHeader( $summaryMessageKey );
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		// Override to prevent conflicts.
		return $this->msg( 'multimail-special-emailaddresses' );
	}

	/** @inheritDoc */
	public function isListed(): bool {
		return $this->getAuthManager()->allowsPropertyChange( 'emailaddress' );
	}

	/** @inheritDoc */
	public function doesWrites(): bool {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'users';
	}

	/** @inheritDoc */
	protected function getLoginSecurityLevel(): string {
		return 'ChangeEmail';
	}
}
