<?php

namespace MediaWiki\Extension\MultiMail\Tests\Structure;

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

	/** @var AbstractSchemaValidator */
	protected AbstractSchemaValidator $validator;

	/** @inheritDoc */
	protected function setUp(): void {
		parent::setUp();

		$this->validator = new AbstractSchemaValidator( [ $this, 'markTestSkipped' ] );
		$this->validator->checkDependencies();
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
	 * @return array
	 */
	public function provideTestables(): array {
		return [
			'tables.json' => [ __DIR__ . '/../../../sql/tables.json' ]
		];
	}
}
