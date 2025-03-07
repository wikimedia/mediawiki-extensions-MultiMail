<?php

namespace MediaWiki\Extension\MultiMail\Tests\Structure;

use MediaWiki\Tests\Structure\AbstractSchemaTestBase;

/**
 * Validates all abstract schemas.
 *
 * @coversNothing
 */
class AbstractSchemaValidationTest extends AbstractSchemaTestBase {

	/** @inheritDoc */
	protected static function getSchemasDirectory(): string {
		return __DIR__ . '/../../../sql';
	}

	/** @inheritDoc */
	protected static function getSchemaChangesDirectory(): string {
		return __DIR__ . '/../../../db_patches/abstractSchemaChanges/';
	}

	/** @inheritDoc */
	protected static function getSchemaSQLDirs(): array {
		return [
			'mysql' => __DIR__ . '/../../../sql/mysql',
			'sqlite' => __DIR__ . '/../../../sql/sqlite',
			'postgres' => __DIR__ . '/../../../sql/postgres',
		];
	}
}
