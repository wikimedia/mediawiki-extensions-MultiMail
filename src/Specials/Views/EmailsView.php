<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use CentralIdLookup;
use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Extension\MultiMail\SpecialPage\Views\View;
use MediaWiki\Extension\MultiMail\Specials\Pager\EmailsPager;
use MediaWiki\Extension\MultiMail\Specials\SpecialEmailAddresses;
use OOUI\ButtonWidget;

class EmailsView extends View {
	private CentralIdLookup $centralIdLookup;

	/**
	 * @codeCoverageIgnore
	 *
	 * @param SpecialEmailAddresses $parent
	 * @param MailManager $mailManager
	 * @param CentralIdLookup $centralIdLookup
	 */
	public function __construct(
		SpecialEmailAddresses $parent,
		MailManager $mailManager,
		CentralIdLookup $centralIdLookup
	) {
		parent::__construct( $parent, $mailManager );

		$this->centralIdLookup = $centralIdLookup;
	}

	/** @inheritDoc */
	public function show( ?string $subpage ): void {
		$out = $this->getOutput();
		$out->addModules( [ 'ext.MultiMail' ] );
		$out->addModuleStyles( [
			// For icon: trash.
			'oojs-ui.styles.icons-moderation',
			// For icon: add.
			'oojs-ui.styles.icons-interaction',
			'ext.MultiMail.styles'
		] );

		$out->addHTML( new ButtonWidget( [
			'name' => 'new',
			'id' => 'ext-multimail-new-email',
			'href' => $this->getPageTitle( 'add' )->getLocalURL(),
			'label' => $this->msg( 'multimail-emails-add-email-button-label' )->plain(),
			'icon' => 'add',
			'flags' => [ 'primary', 'progressive' ],
			'infusable' => true
		] ) );

		$this->outputHeader( 'multimail-special-emailaddresses-summary' );
		$pager = new EmailsPager(
			$this->getContext(),
			$this->centralIdLookup,
			$this->mailManager->getReplicaMailDbConnection()
		);

		$out->addParserOutputContent( $pager->getFullOutput() );
	}
}
