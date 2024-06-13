<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Extension\MultiMail\SpecialPage\Views\FormView;
use MediaWiki\MainConfigNames;
use OOUIHTMLForm;
use Status;
use function strlen;
use function substr;

class AddEmailView extends FormView {
	private bool $emailRequiresConfirming;

	private string $prefill;

	/** @inheritDoc */
	public function beforeForm( ?string $subpage ): void {
		$this->getOutput()->setPageTitleMsg( $this->msg( 'multimail-special-add-email-view' ) );

		$this->emailRequiresConfirming = $this->getConfig()->get( MainConfigNames::EmailAuthentication );

		// Trim add(/).
		$this->prefill = substr( $subpage, strlen( $subpage ) > 3 ? 4 : 3 );
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		return [
			'email' => [
				'type' => 'email',
				'label-message' => 'multimail-special-add-email-view-new-email-label-message',
				'validation-callback' => [ $this->mailManager, 'validateEmailAddress' ],
				'maxlength' => 255,
				'autofocus' => true,
				'default' => $this->prefill
			]
		];
	}

	/** @inheritDoc */
	protected function alterForm( OOUIHTMLForm $form ): void {
		if ( $this->emailRequiresConfirming ) {
			$header = 'multimail-special-add-email-view-with-confirmation-summary';
		} else {
			$header = 'multimail-special-add-email-view-summary';
		}

		$form
			->setPreHtml( $this->msg( $header )->parse() )
			->setSubmitTextMsg( 'multimail-special-add-email-view-submit-button-label-message' )
			// Used messages:
			// tooltip-multimail-special-add-email-view-submit
			// access-multimail-special-add-email-view-submit
			->setSubmitTooltip( 'multimail-special-add-email-view-submit' );
	}

	/** @inheritDoc */
	protected function onSubmit( array $data ): Status {
		if ( $this->getUser()->pingLimiter( 'changeemail' ) ) {
			return Status::newFatal( 'actionthrottledtext' );
		}

		return $this->mailManager->addEmailAndSendConfirmationEmail( $data['email'], $this->getContext() );
	}

	/** @inheritDoc */
	protected function onSuccess(): void {
		$out = $this->getOutput();

		if ( $this->emailRequiresConfirming ) {
			$out->addWikiMsg( 'multimail-special-add-email-view-confirmation-sent' );
			$out->returnToMain( null, $this->getPageTitle() );
		} else {
			$out->redirect( $this->getPageTitle()->getLocalURL() );
		}
	}
}
