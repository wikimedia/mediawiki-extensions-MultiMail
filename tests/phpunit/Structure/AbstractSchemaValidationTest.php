<?php

namespace MediaWiki\Extension\MultiMail\Tests\Structure;

use Generator;
use MediaWiki\DB\AbstractSchemaValidator;
use MediaWikiCoversValidator;
use PHPUnit\Framework\TestCase;

/**
 * Validates all abstract schemas.
 *
 * @coversNothing
 */
class AbstractSchemaValidationTest extends TestCase {
	use MediaWikiCoversValidator;

	protected AbstractSchemaValidator $validator;

	/** @inheritDoc */
	protected function setUp(): void {
		parent::setUp();

		$this->validator = new AbstractSchemaValidator();
	}

	/**
	 * @doesNotPerformAssertions
	 *
	 * @dataProvider provideTestables
	 *
	 * @param string $path
	 */
	public function testValidation( string $path ): void {
		$this->validator->validate( $path );
	}

	/**
	 * Data provider for testValidation.
	 *
	 * @return Generator
	 */
	public static function provideTestables(): Generator {
		yield 'tables.json' => [ __DIR__ . '/../../../sql/tables.json' ];

		foreach ( glob( __DIR__ . '/../../../sql/abstractSchemaChanges/*.json' ) as $schemaChange ) {
			$fileName = pathinfo( $schemaChange, PATHINFO_BASENAME );

			yield $fileName => [ $schemaChange ];
		}
	}
}
