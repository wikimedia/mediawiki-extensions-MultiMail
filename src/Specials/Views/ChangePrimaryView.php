<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Extension\MultiMail\HTMLForm\Fields\HTMLMessageField;
use MediaWiki\Extension\MultiMail\SpecialPage\Views\ConfirmationView;
use OOUIHTMLForm;
use Status;

class ChangePrimaryView extends ConfirmationView {
	/** @inheritDoc */
	protected function beforeForm( ?string $subpage ): void {
		$this->getOutput()->setPageTitleMsg( $this->msg( 'multimail-special-change-primary-view' ) );
	}

	/** @inheritDoc */
	protected function alterForm( OOUIHTMLForm $form ): void {
		$form
			->setSubmitTextMsg( 'multimail-special-change-primary-view-confirmation-submit-label-message' )
			// Used messages:
			// tooltip-multimail-special-change-primary-view-confirmation-submit
			// access-multimail-special-change-primary-view-confirmation-submit
			->setSubmitTooltip( 'multimail-special-change-primary-view-confirmation-submit' );
	}

	/** @inheritDoc */
	protected function onSubmit( array $data ): Status {
		if ( $this->getUser()->pingLimiter( 'changeemail' ) ) {
			return Status::newFatal( 'actionthrottledtext' );
		}

		return $this->mailManager->makePrimary( $this->secondaryEmail, $this->getContext() );
	}

	/** @inheritDoc */
	protected function getRequiredAuthenticationStatus(): ?bool {
		return self::AUTHENTICATED_ONLY;
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		return [
			'confirm' => [
				'class' => HTMLMessageField::class,
				'messagetype' => 'warning',
				'message' => [
					'multimail-special-change-primary-view-confirmation',
					$this->getUser()->getEmail(),
					$this->secondaryEmail->getEmail()
				]
			]
		];
	}
}
