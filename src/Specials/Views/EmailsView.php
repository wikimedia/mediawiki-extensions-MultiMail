<?php

namespace MediaWiki\Extension\MultiMail\Specials\Views;

use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Extension\MultiMail\SpecialPage\Views\View;
use MediaWiki\Extension\MultiMail\Specials\Pager\EmailsPager;
use MediaWiki\Extension\MultiMail\Specials\SpecialEmailAddresses;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\User\CentralId\CentralIdLookup;
use OOUI\ButtonWidget;

class EmailsView extends View {
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct(
		SpecialEmailAddresses $parent,
		MailManager $mailManager,
		private readonly CentralIdLookup $centralIdLookup,
	) {
		parent::__construct( $parent, $mailManager );
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

		$out->addHTML( (string)new ButtonWidget( [
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

		$out->addParserOutputContent(
			$pager->getFullOutput(),
			ParserOptions::newFromContext( $this->getContext() )
		);
	}
}
