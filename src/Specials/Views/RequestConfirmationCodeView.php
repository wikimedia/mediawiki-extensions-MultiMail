<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Extension\MultiMail\HTMLForm\Fields\HTMLMessageField;
use MediaWiki\Extension\MultiMail\SpecialPage\Views\ConfirmationView;
use MediaWiki\HTMLForm\OOUIHTMLForm;
use MediaWiki\Status\Status;

class RequestConfirmationCodeView extends ConfirmationView {
	/** @inheritDoc */
	protected function beforeForm( ?string $subpage ): void {
		$this->getOutput()->setPageTitleMsg( $this->msg( 'multimail-special-request-confirmation-code-view' ) );
	}

	/** @inheritDoc */
	protected function onSubmit( array $data ): Status {
		return $this->mailManager->sendConfirmationMail( $this->secondaryEmail, $this->getContext() );
	}

	/** @inheritDoc */
	protected function alterForm( OOUIHTMLForm $form ): void {
		$form->setSubmitTextMsg( 'multimail-special-request-confirmation-code-view-submit' )
			// Used messages:
			// tooltip-multimail-special-request-confirmation-code-view-submit
			// access-multimail-special-request-confirmation-code-view-submit
			->setSubmitTooltip( 'multimail-special-request-confirmation-code-view-submit' );
	}

	/** @inheritDoc */
	protected function onSuccess(): void {
		$out = $this->getOutput();

		$out->addWikiMsg( 'multimail-special-request-confirmation-code-view-confirmation-email-sent' );
		$out->addReturnTo( $this->getPageTitle() );
	}

	/** @inheritDoc */
	protected function getRequiredAuthenticationStatus(): ?bool {
		return self::UNAUTHENTICATED_ONLY;
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		$descriptor = [];

		if ( $this->secondaryEmail->isEmailConfirmationPending() ) {
			$descriptor += [
				'pending' => [
					'class' => HTMLMessageField::class,
					'message' => 'multimail-special-request-confirmation-code-view-confirmation-email-pending'
				]
			];
		}

		return $descriptor;
	}
}
