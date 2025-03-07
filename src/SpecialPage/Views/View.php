<?php

namespace MediaWiki\Extension\MultiMail\SpecialPage\Views;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Extension\MultiMail\Specials\SpecialEmailAddresses;
use MediaWiki\Message\Message;
use MediaWiki\Output\OutputPage;
use MediaWiki\Request\WebRequest;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use Wikimedia\Message\MessageSpecifier;

/**
 * @codeCoverageIgnore
 */
abstract class View {
	private SpecialEmailAddresses $parent;

	protected MailManager $mailManager;

	/**
	 * @param SpecialEmailAddresses $parent
	 * @param MailManager $mailManager
	 */
	public function __construct( SpecialEmailAddresses $parent, MailManager $mailManager ) {
		$this->parent = $parent;
		$this->mailManager = $mailManager;
	}

	/**
	 * Main view execution method, like @see SpecialPage::execute
	 *
	 * @param string|null $subpage
	 */
	abstract public function show( ?string $subpage ): void;

	/**
	 * Shortcut to get main config object.
	 *
	 * @return Config
	 */
	protected function getConfig(): Config {
		return $this->parent->getConfig();
	}

	/**
	 * Gets the context this View is executed in.
	 *
	 * @return IContextSource
	 */
	protected function getContext(): IContextSource {
		return $this->parent->getContext();
	}

	/**
	 * Get the OutputPage being used for this view.
	 *
	 * @return OutputPage
	 */
	protected function getOutput(): OutputPage {
		return $this->parent->getOutput();
	}

	/**
	 * Get the WebRequest being used for this view.
	 *
	 * @return WebRequest
	 */
	protected function getRequest(): WebRequest {
		return $this->parent->getRequest();
	}

	/**
	 * Shortcut to get the User executing this view.
	 *
	 * @return User
	 */
	protected function getUser(): User {
		return $this->parent->getUser();
	}

	/**
	 * Get a self-referential title object to the special page.
	 *
	 * @param string|bool $subpage
	 * @return Title
	 */
	protected function getPageTitle( $subpage = false ): Title {
		return $this->parent->getPageTitle( $subpage );
	}

	/**
	 * Shortcut to SpecialPage::outputHeader.
	 * This time, $summaryMessageKey is required as views calling this method must
	 * use a different message key than the default.
	 *
	 * @param string $summaryMessageKey Message key of the summary
	 */
	protected function outputHeader( string $summaryMessageKey ): void {
		$this->parent->outputHeader( $summaryMessageKey );
	}

	/**
	 * This is the method for getting translated interface messages.
	 *
	 * @param string|string[]|MessageSpecifier $key
	 * @param mixed ...$params
	 * @return Message
	 */
	protected function msg( $key, ...$params ): Message {
		return $this->parent->msg( $key, ...$params );
	}
}
