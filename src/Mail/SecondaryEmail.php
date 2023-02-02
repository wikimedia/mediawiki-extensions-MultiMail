<?php

namespace MediaWiki\Extension\MultiMail\Mail;

use MediaWiki\Mail\UserEmailContact;
use MediaWiki\User\UserIdentity;
use MWCryptRand;
use MWTimestamp;
use stdClass;
use User;
use function md5;
use function wfTimestamp;
use const TS_MW;

class SecondaryEmail implements UserEmailContact {
	private User $user;

	private int $id;

	private string $emailAddress;

	private ?string $emailAuthenticationTimestamp;

	private ?string $emailToken;

	private ?string $emailTokenExpires;

	private bool $emailAuthenticationEnabled;

	private int $userEmailConfirmationTokenExpiry;

	/**
	 * Creates a new secondary email address.
	 *
	 * @param User $user User to whom this email address belongs
	 * @param stdClass $row Row in the database
	 * @param bool $emailAuthenticationEnabled If $wgEmailAuthentication is enabled
	 * @param int $userEmailConfirmationTokenExpiry Life time of confirmation tokens
	 */
	public function __construct(
		User $user,
		stdClass $row,
		bool $emailAuthenticationEnabled,
		int $userEmailConfirmationTokenExpiry
	) {
		$this->user = $user;
		$this->id = $row->use_id;
		$this->emailAddress = $row->use_email;
		$this->emailAuthenticationTimestamp = $row->use_email_authenticated;
		$this->emailToken = $row->use_email_token;
		$this->emailTokenExpires = $row->use_email_token_expires;
		$this->emailAuthenticationEnabled = $emailAuthenticationEnabled;
		$this->userEmailConfirmationTokenExpiry = $userEmailConfirmationTokenExpiry;
	}

	/**
	 * Returns the id of the row in the secondary email table.
	 *
	 * @return int
	 */
	public function getId(): int {
		return $this->id;
	}

	/** @inheritDoc */
	public function getUser(): UserIdentity {
		return $this->user;
	}

	/** @inheritDoc */
	public function getEmail(): string {
		return $this->emailAddress;
	}

	/** @inheritDoc */
	public function getRealName(): string {
		return $this->user->getRealName();
	}

	/** @inheritDoc */
	public function isEmailConfirmed(): bool {
		return !$this->emailAuthenticationEnabled || $this->emailAuthenticationTimestamp;
	}

	/**
	 * Check whether there is an outstanding request for email confirmation.
	 *
	 * @return bool
	 */
	public function isEmailConfirmationPending(): bool {
		return !$this->isEmailConfirmed() &&
			$this->emailToken &&
			$this->emailTokenExpires > wfTimestampNow();
	}

	/**
	 * Get the timestamp of the email authentication.
	 *
	 * @return string|null TS_MW timestamp or null if not yet authenticated
	 */
	public function getEmailAuthenticationTimestamp(): ?string {
		return $this->emailAuthenticationTimestamp;
	}

	/**
	 * Generate a new email confirmation token.
	 *
	 * @return string[] Containing token, expiry, hashed token
	 */
	public function generateNewConfirmationToken(): array {
		$expires = MWTimestamp::time() + $this->userEmailConfirmationTokenExpiry;
		$token = MWCryptRand::generateHex( 32 );
		$this->emailToken = md5( $token );
		$this->emailTokenExpires = wfTimestamp( TS_MW, $expires );

		return [ $token, $this->emailTokenExpires, $this->emailToken ];
	}
}
