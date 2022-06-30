<?php

namespace MediaWiki\Extension\MultiMail\SpecialPage\Views;

use ErrorPageError;
use MediaWiki\Extension\MultiMail\Mail\SecondaryEmail;
use function preg_match;

abstract class ConfirmationView extends FormView {
	protected const UNAUTHENTICATED_ONLY = false;
	protected const AUTHENTICATED_ONLY = true;
	protected const AUTHENTICATED_BOTH = null;

	/** @var int */
	protected $id;

	/** @var SecondaryEmail */
	protected $secondaryEmail;

	/** @inheritDoc */
	public function show( ?string $subpage ): void {
		if ( preg_match( '/^[a-z]+\/([1-9][0-9]*)$/', $subpage ?? '', $matches ) ) {
			$this->id = (int)$matches[1][0];
		} else {
			$this->id = -1;
		}

		if ( $this->id < 0 ) {
			throw new ErrorPageError(
				'multimail-emails-invalid-id',
				'multimail-emails-invalid-id-body'
			);
		}

		$requiredAuthenticationStatus = $this->getRequiredAuthenticationStatus();

		$secondaryEmail = $this->mailManager->getEmailFromId( $this->id, $this->getUser() );

		if (
			!$secondaryEmail ||
			(
				$requiredAuthenticationStatus !== self::AUTHENTICATED_BOTH &&
				$secondaryEmail->isEmailConfirmed() !== $requiredAuthenticationStatus
			)
		) {
			throw new ErrorPageError(
				'multimail-emails-invalid-id',
				'multimail-emails-invalid-id-body'
			);
		}

		$this->secondaryEmail = $secondaryEmail;

		parent::show( $subpage );
	}

	/** @inheritDoc */
	protected function onSuccess(): void {
		$this->getOutput()->redirect( $this->getPageTitle()->getLocalURL() );
	}

	/**
	 * Returns the status of authentication that this view requires the given secondary email address to have.
	 *
	 * @return bool|null One of the constants in this class
	 */
	abstract protected function getRequiredAuthenticationStatus(): ?bool;
}
