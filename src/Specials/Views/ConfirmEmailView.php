<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Exception\ThrottledError;
use MediaWiki\Extension\MultiMail\SpecialPage\Views\View;
use MediaWiki\Profiler\Profiler;
use MediaWiki\User\User;
use Wikimedia\ScopedCallback;
use function explode;
use function preg_match;

class ConfirmEmailView extends View {
	/** @inheritDoc */
	public function show( ?string $subpage ): void {
		$out = $this->getOutput();

		if ( !str_starts_with( $subpage ?? '', 'confirm/' ) || substr_count( $subpage, '/' ) < 2 ) {
			$out->redirect( $this->getPageTitle()->getLocalURL() );

			return;
		}

		[ , $id, $token ] = explode( '/', $subpage, 2 );

		if (
			!preg_match( '/[1-9][0-9]*/', $id ) ||
			!User::isWellFormedConfirmationToken( $token )
		) {
			$out->addWikiMsg( 'multimail-emails-confirm-broken-token' );

			return;
		}

		if ( $this->getUser()->pingLimiter( 'confirmemail' ) ) {
			throw new ThrottledError();
		}

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
