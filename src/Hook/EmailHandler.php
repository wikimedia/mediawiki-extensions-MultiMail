<?php

namespace MediaWiki\Extension\MultiMail\Hook;

use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Hook\PrefsEmailAuditHook;
use MediaWiki\Parser\Sanitizer;
use MediaWiki\User\Hook\ConfirmEmailCompleteHook;

class EmailHandler implements ConfirmEmailCompleteHook, PrefsEmailAuditHook {
	private static bool $isCalledFromMultiMail = false;

	private MailManager $mailManager;

	/**
	 * @codeCoverageIgnore
	 *
	 * @param MailManager $mailManager
	 */
	public function __construct( MailManager $mailManager ) {
		$this->mailManager = $mailManager;
	}

	/**
	 * Setter to indicate a hook call made from MultiMail code, which should not execute any of the hook.
	 */
	public static function setCalledFromMultiMail(): void {
		self::$isCalledFromMultiMail = true;
	}

	/**
	 * Helper to inline checking for $isCalledFromMultiMail while also resetting its value.
	 *
	 * @return bool
	 */
	private static function isCalledFromMultiMail(): bool {
		if ( self::$isCalledFromMultiMail ) {
			self::$isCalledFromMultiMail = false;

			return true;
		}

		return false;
	}

	/** @inheritDoc */
	public function onConfirmEmailComplete( $user ): void {
		$secondaryEmail = $this->mailManager->getEmailFromAddress( $user->getEmail(), $user );

		if ( !$secondaryEmail ) {
			return;
		}

		// User::saveSettings has not yet been called, so this is still the old value.
		$this->mailManager->updateAuthenticationStatus( $secondaryEmail, $user->getEmailAuthenticationTimestamp() );
	}

	/** @inheritDoc */
	public function onPrefsEmailAudit( $user, $oldaddr, $newaddr ): void {
		if ( $newaddr === '' || self::isCalledFromMultiMail() || !Sanitizer::validateEmail( $oldaddr ) ) {
			return;
		}

		$secondaryEmail = $this->mailManager->getEmailFromAddress( $oldaddr, $user );

		if ( !$secondaryEmail ) {
			$status = $this->mailManager->addEmail( $oldaddr, $user );

			if ( !$status->isGood() ) {
				return;
			}

			$secondaryEmail = $status->getValue();
		}

		// User::saveSettings has not yet been called, so this is still the old value.
		$this->mailManager->updateAuthenticationStatus( $secondaryEmail, $user->getEmailAuthenticationTimestamp() );
	}
}
