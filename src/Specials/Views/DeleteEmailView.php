<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Extension\MultiMail\HTMLForm\Fields\HTMLMessageField;
use MediaWiki\Extension\MultiMail\SpecialPage\Views\ConfirmationView;
use MediaWiki\HTMLForm\OOUIHTMLForm;
use MediaWiki\Status\Status;

class DeleteEmailView extends ConfirmationView {
	/** @inheritDoc */
	protected function beforeForm( ?string $subpage ): void {
		$this->getOutput()->setPageTitleMsg(
			$this->msg( 'multimail-special-delete-view', $this->secondaryEmail->getEmail() )
		);
	}

	/** @inheritDoc */
	protected function alterForm( OOUIHTMLForm $form ): void {
		$form
			->setSubmitDestructive()
			->setSubmitTextMsg( 'multimail-special-delete-view-confirmation-submit-label-message' )
			// Used messages:
			// tooltip-multimail-special-delete-view-confirmation-submit
			// access-multimail-special-delete-view-confirmation-submit
			->setSubmitTooltip( 'multimail-special-delete-view-confirmation-submit' );
	}

	/** @inheritDoc */
	protected function onSubmit( array $data ): Status {
		return $this->mailManager->delete( $this->secondaryEmail );
	}

	/** @inheritDoc */
	protected function getRequiredAuthenticationStatus(): ?bool {
		return self::AUTHENTICATED_BOTH;
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		return [
			'confirm' => [
				'class' => HTMLMessageField::class,
				'messagetype' => 'warning',
				'message' => [
					'multimail-special-delete-view-confirmation',
					$this->secondaryEmail->getEmail()
				]
			]
		];
	}
}
