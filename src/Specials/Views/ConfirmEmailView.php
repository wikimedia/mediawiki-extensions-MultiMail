<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Extension\MultiMail\SpecialPage\Views\View;
use Profiler;
use ThrottledError;
use Wikimedia\ScopedCallback;
use function preg_match;

class ConfirmEmailView extends View {
	/** @inheritDoc */
	public function show( ?string $subpage ): void {
		$out = $this->getOutput();

		if ( !preg_match( '/^confirm\/([1-9][0-9]*)\/([a-z0-9]{32})$/', $subpage ?? '', $matches ) ) {
			// This is not a typical user-visible end-point, being a sub-page,
			// so just redirect back to main on malformed urls.
			$out->redirect( $this->getPageTitle()->getLocalURL() );
		}

		if ( $this->getUser()->pingLimiter( 'confirmemail' ) ) {
			throw new ThrottledError();
		}

		list( , $id, $token ) = $matches;

		$scope = Profiler::instance()->getTransactionProfiler()->silenceForScope();

		if ( $this->mailManager->confirm( (int)$id, $token, $this->getUser() ) ) {
			$out->addWikiMsg( 'multimail-emails-confirm-success' );
		} else {
			$out->addWikiMsg( 'multimail-emails-confirm-invalid' );
		}

		$out->addReturnTo( $this->getPageTitle() );

		ScopedCallback::consume( $scope );
	}
}
