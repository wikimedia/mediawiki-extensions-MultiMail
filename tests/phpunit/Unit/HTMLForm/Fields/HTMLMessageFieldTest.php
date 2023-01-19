<?php

namespace MediaWiki\Extension\MultiMail\Tests\Unit\HTMLForm\Fields;

use HTMLForm;
use MediaWiki\Extension\MultiMail\HTMLForm\Fields\HTMLMessageField;
use MediaWikiUnitTestCase;
use Message;

/**
 * @covers \MediaWiki\Extension\MultiMail\HTMLForm\Fields\HTMLMessageField
 */
class HTMLMessageFieldTest extends MediaWikiUnitTestCase {
	/**
	 * Test that the given message will be escaped when parse is not defined in the config.
	 */
	public function testMessageIsEscaped(): void {
		$config = [
			'fieldname' => 'test',
			'message' => '',
			'parent' => $this->createMock( HTMLForm::class )
		];
		$mockMessage = $this->createMock( Message::class );
		$mockMessage
			->expects( static::once() )
			->method( 'toString' )
			->with( Message::FORMAT_ESCAPED )
			->willReturn( '' );

		$field = new class( $config, $mockMessage ) extends HTMLMessageField {
			/** @var Message */
			private $mockMessage;

			public function __construct( array $info, Message $mockMessage ) {
				parent::__construct( $info );

				$this->mockMessage = $mockMessage;
			}

			/** @inheritDoc */
			public function msg( $key, ...$params ): Message {
				return $this->mockMessage;
			}
		};

		$field->getDefault();
	}

	/**
	 * Test that when parse is specified, the message will be treated as wiki text.
	 */
	public function testMessageIsParsed(): void {
		$config = [
			'fieldname' => 'test',
			'message' => '',
			'parse' => true,
			'parent' => $this->createMock( HTMLForm::class )
		];
		$mockMessage = $this->createMock( Message::class );
		$mockMessage
			->expects( static::once() )
			->method( 'toString' )
			->with( Message::FORMAT_PARSE )
			->willReturn( '' );

		$field = new class( $config, $mockMessage ) extends HTMLMessageField {
			/** @var Message */
			private $mockMessage;

			public function __construct( array $info, Message $mockMessage ) {
				parent::__construct( $info );

				$this->mockMessage = $mockMessage;
			}

			/** @inheritDoc */
			public function msg( $key, ...$params ): Message {
				return $this->mockMessage;
			}
		};

		$field->getDefault();
	}

	/**
	 * Test that optional config is only provided when given, and that the default type is notice.
	 *
	 * @dataProvider provideConfig
	 *
	 * @param array $config
	 * @param array $expected
	 */
	public function testGetInputOOUI( array $config, array $expected ): void {
		$field = new HTMLMessageField( $config + [
			'fieldname' => 'test',
			'message' => '',
			'parent' => $this->createMock( HTMLForm::class )
		] );

		$widget = $field->getInputOOUI( '' );
		$config = [];

		$widget->getConfig( $config );
		static::assertEquals( $expected, $config );
	}

	/**
	 * Data provider for testGetInputOOUI.
	 *
	 * @return array
	 */
	public function provideConfig(): array {
		return [
			'No type specified' => [
				[],
				[
					'type' => 'notice',
					'inline' => false,
					'icon' => 'infoFilled',
					'flags' => [
						'notice'
					],
					'showClose' => false
				]
			],
			'With class' => [
				[ 'cssclass' => 'testclass' ],
				[
					'classes' => [ 'testclass' ],
					'type' => 'notice',
					'inline' => false,
					'icon' => 'infoFilled',
					'flags' => [
						'notice'
					],
					'showClose' => false
				]
			],
			'Type warning' => [
				[ 'messagetype' => 'warning' ],
				[
					'type' => 'warning',
					'inline' => false,
					'icon' => 'alert',
					'flags' => [
						'warning'
					],
					'showClose' => false
				]
			]
		];
	}
}
