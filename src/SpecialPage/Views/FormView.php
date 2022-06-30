<?php

namespace MediaWiki\Extension\MultiMail\SpecialPage\Views;

use OOUIHTMLForm;
use Status;

abstract class FormView extends View {
	/** @inheritDoc */
	public function show( ?string $subpage ): void {
		$this->beforeForm( $subpage );

		$form = ( new OOUIHTMLForm( $this->getFormFields(), $this->getContext() ) )
			->setSubmitCallback( function ( array $data ): Status {
				return $this->onSubmit( $data );
			} )
			->setTokenSalt( 'multimail' )
			->showCancel()
			->setCancelTarget( $this->getPageTitle() );

		// @phan-suppress-next-line PhanTypeMismatchArgumentSuperType HTMLForm return types are too narrow.
		$this->alterForm( $form );

		if ( $form->show() ) {
			$this->onSuccess();
		}
	}

	/**
	 * Things to do (and show) before the form.
	 *
	 * @param string|null $subpage
	 * phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
	 */
	protected function beforeForm( ?string $subpage ): void {}

	/**
	 * Adjust the HTMLForm.
	 *
	 * @param OOUIHTMLForm $form
	 * phpcs:disable Squiz.WhiteSpace.ScopeClosingBrace.ContentBefore
	 */
	protected function alterForm( OOUIHTMLForm $form ): void {}

	/**
	 * Get the HTMLForm descriptor.
	 *
	 * @return array
	 */
	abstract protected function getFormFields(): array;

	/**
	 * Process the form on submission.
	 *
	 * @param array $data
	 * @return Status
	 */
	abstract protected function onSubmit( array $data ): Status;

	/**
	 * Do something after successfully processing the form.
	 */
	abstract protected function onSuccess(): void;
}
