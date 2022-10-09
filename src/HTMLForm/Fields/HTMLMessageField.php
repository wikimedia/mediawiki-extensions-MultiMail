<?php

namespace MediaWiki\Extension\MultiMail\HTMLForm\Fields;

use HTMLFormField;
use InvalidArgumentException;
use Message;
use MessageSpecifier;
use MWException;
use OOUI\HtmlSnippet;
use OOUI\MessageWidget;

/**
 * HTMLFormField that shows the OOUI MessageWidget
 * Four types are supported; success, notice, warning and error.
 */
class HTMLMessageField extends HTMLFormField {
	/**
	 * Type of the message box.
	 * Either success, notice, warning or error.
	 *
	 * @var string
	 */
	private $type;

	/**
	 * Message to use in the message box.
	 *
	 * @var string[]|array[]|MessageSpecifier[]
	 * @phan-var non-empty-array<string|array|MessageSpecifier>
	 */
	private $message;

	/**
	 * If the message should be parsed as wikitext.
	 * If false, the message will be escaped.
	 *
	 * @var bool
	 */
	private $parse;

	/**
	 * @param array $info
	 *   In addition to the usual HTMLFormField parameters the following additional fields are
	 * supported:
	 *   - message: (required) message to use as the display text. Either a string, array or
	 *    MessageSpecifier object
	 *   - messagetype: type of the message. Supported types are success, notice, warning and error.
	 *    Defaults to notice.
	 *   - parse: if the message should be parsed, instead of escaped.
	 * @throws MWException
	 */
	public function __construct( array $info ) {
		$this->type = $info['messagetype'] ?? 'notice';
		if ( $info['message'] instanceof MessageSpecifier || is_string( $info['message'] ) ) {
			$this->message = [ $info['message'] ];
		} elseif ( is_array( $info['message'] ) && $info['message'] ) {
			$this->message = $info['message'];
		} else {
			throw new InvalidArgumentException( 'Invalid "message" field.' );
		}
		$this->parse = $info['parse'] ?? false;

		$info['nodata'] = true;
		parent::__construct( $info );
	}

	/** @inheritDoc */
	public function getDefault(): string {
		// @phan-suppress-next-line PhanParamTooFewUnpack Should infer non-emptiness from docblock
		return $this->msg( ...$this->message )
			->toString( $this->parse ? Message::FORMAT_PARSE : Message::FORMAT_ESCAPED );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function getInputHTML( $value ): string {
		$this->mParent->getOutput()->enableOOUI();

		return (string)$this->getInputOOUI( $value );
	}

	/** @inheritDoc */
	public function getInputOOUI( $value ): MessageWidget {
		$config = [
			'name' => $this->mName,
			'type' => $this->type,
			'label' => new HtmlSnippet( $value ),
			'id' => $this->mID
		];

		if ( $this->mClass !== '' ) {
			$config['classes'] = [ $this->mClass ];
		}

		return new MessageWidget( $config );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	protected function needsLabel(): bool {
		return false;
	}
}
