<?php

namespace MediaWiki\Extension\MultiMail\Tests\Integration\Hook;

use DatabaseUpdater;
use MediaWiki\Extension\MultiMail\Hook\SetupHandler;
use MediaWiki\MediaWikiServices;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\Constraint\StringEndsWith;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\Extension\MultiMail\Hook\SetupHandler
 */
class SetupHandlerTest extends MediaWikiIntegrationTestCase {
	private function getMockUpdater() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'getDomainID' )->willReturn( 'testDB' );

		$mock = $this->createMock( DatabaseUpdater::class );
		$mock->method( 'getDB' )->willReturn( $db );

		return $mock;
	}

	/**
	 * This test simulates a single wiki environment.
	 */
	public function testOnLoadExtensionSchemaUpdates(): void {
		$this->setMwGlobals( [
			'wgMultiMailDB' => false
		] );

		$mock = $this->getMockUpdater();
		$mock->expects( static::once() )->method( 'addExtensionTable' )->with(
			'user_secondary_email',
			new StringEndsWith( 'tables-generated.sql' )
		);

		( new SetupHandler() )->onLoadExtensionSchemaUpdates( $mock );
	}

	/**
	 * This test simulates a multi wiki environment.
	 */
	public function testOnLoadExtensionSchemaUpdatesForMultiWiki(): void {
		$this->setMwGlobals( [
			'wgMultiMailDB' => 'testDB'
		] );

		$mock = $this->getMockUpdater();
		$mock->expects( static::once() )->method( 'addExtensionTable' )->with(
			'user_secondary_email',
			new StringEndsWith( 'tables-generated.sql' )
		);
		( new SetupHandler() )->onLoadExtensionSchemaUpdates( $mock );
	}

	/**
	 * Test that when $wgMultiMailDB is set to a different database name than the
	 * current database, no tables are added.
	 */
	public function testOnLoadExtensionSchemaUpdatesForMismatchingDbName(): void {
		$this->setMwGlobals( [
			'wgMultiMailDB' => 'not_testDB'
		] );

		$mock = $this->getMockUpdater();
		$mock->expects( static::never() )->method( 'addExtensionTable' );

		( new SetupHandler() )->onLoadExtensionSchemaUpdates( $mock );
	}

	/**
	 * Test that when the installer is (simulated) to be run,
	 * the hook should just operate on the defaults.
	 */
	public function testOnSetup(): void {
		// Set configuration that normally would result in no tables added.
		$this->setMwGlobals( [
			'wgConfigurationDatabase' => 'not_testDB'
		] );

		// Sanity check - disable the config factory.
		// This will throw an exception if the hook does use it.
		$services = MediaWikiServices::getInstance();
		$services->disableService( 'ConfigFactory' );

		$mock = $this->getMockUpdater();
		$mock->expects( static::once() )->method( 'addExtensionTable' );

		( new SetupHandler( true ) )->onLoadExtensionSchemaUpdates( $mock );
	}
}
