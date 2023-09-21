<?php

namespace MediaWiki\Extension\MultiMail\Tests\Integration\Hook;

use MediaWiki\Extension\MultiMail\Hook\EmailHandler;
use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Extension\MultiMail\Mail\SecondaryEmail;
use MediaWiki\Status\Status;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use function wfTimestampNow;

/**
 * @covers \MediaWiki\Extension\MultiMail\Hook\EmailHandler
 */
class EmailHandlerTest extends MediaWikiIntegrationTestCase {
	public function testOnConfirmEmailCompleteNoSecondaryEmailFound(): void {
		$manager = $this->createNoOpMock( MailManager::class, [ 'getEmailFromAddress' ] );
		$manager->method( 'getEmailFromAddress' )->willReturn( null );

		$user = $this->createMock( User::class );
		$user->method( 'getEmail' )->willReturn( 'user@test' );

		$handler = new EmailHandler( $manager );
		$handler->onConfirmEmailComplete( $user );
	}

	public function testOnConfirmEmailComplete(): void {
		$timestamp = wfTimestampNow();
		$email = $this->createMock( SecondaryEmail::class );

		$manager = $this->createNoOpMock( MailManager::class, [ 'getEmailFromAddress', 'updateAuthenticationStatus' ] );
		$manager->method( 'getEmailFromAddress' )->willReturn( $email );
		$manager->method( 'updateAuthenticationStatus' )->with( $email, $timestamp );

		$user = $this->createMock( User::class );
		$user->method( 'getEmail' )->willReturn( 'user@test.com' );
		$user->method( 'getEmailAuthenticationTimestamp' )->willReturn( $timestamp );

		$handler = new EmailHandler( $manager );
		$handler->onConfirmEmailComplete( $user );
	}

	public function testOnPrefsEmailAuditWithInvalidOldAddress(): void {
		$manager = $this->createNoOpMock( MailManager::class );

		$handler = new EmailHandler( $manager );
		$handler->onPrefsEmailAudit( $this->createMock( User::class ), '', 'user@test.com' );
	}

	public function testOnPrefsEmailAuditRemovalOfEmail(): void {
		$manager = $this->createNoOpMock( MailManager::class );

		$handler = new EmailHandler( $manager );
		$handler->onPrefsEmailAudit( $this->createMock( User::class ), 'user@test.com', '' );
	}

	public function testOnPrefsEmailAuditMultiMailOverride(): void {
		$manager = $this->createNoOpMock( MailManager::class );

		EmailHandler::setCalledFromMultiMail();

		$handler = new EmailHandler( $manager );
		$handler->onPrefsEmailAudit( $this->createMock( User::class ), 'old@test.com', 'user@test.com' );
	}

	public function testOnPrefsEmailAuditNotInDatabase(): void {
		$timestamp = wfTimestampNow();

		$user = $this->createMock( User::class );
		$user->method( 'getEmailAuthenticationTimestamp' )->willReturn( $timestamp );

		$email = $this->createMock( SecondaryEmail::class );

		$manager = $this->createMock( MailManager::class );
		$manager
			->expects( static::once() )
			->method( 'getEmailFromAddress' )
			->with( 'old@test.com', $user )
			->willReturn( null );
		$manager
			->expects( static::once() )
			->method( 'addEmail' )
			->with( 'old@test.com', $user )
			->willReturn( Status::newGood( $email ) );
		$manager
			->expects( static::once() )
			->method( 'updateAuthenticationStatus' )
			->with( $email, $timestamp );

		$handler = new EmailHandler( $manager );
		$handler->onPrefsEmailAudit( $user, 'old@test.com', 'user@test.com' );
	}

	public function testOnPrefsEmailAuditNotInDatabaseAndAddingFailed(): void {
		$user = $this->createMock( User::class );

		$manager = $this->createMock( MailManager::class );
		$manager
			->expects( static::once() )
			->method( 'getEmailFromAddress' )
			->willReturn( null );
		$manager
			->expects( static::once() )
			->method( 'addEmail' )
			->willReturn( Status::newFatal( 'test' ) );
		$manager
			->expects( static::never() )
			->method( 'updateAuthenticationStatus' );

		$handler = new EmailHandler( $manager );
		$handler->onPrefsEmailAudit( $user, 'old@test.com', 'user@test.com' );
	}

	public function testOnPrefsEmailAudit(): void {
		$timestamp = wfTimestampNow();

		$user = $this->createMock( User::class );
		$user->method( 'getEmailAuthenticationTimestamp' )->willReturn( $timestamp );

		$email = $this->createMock( SecondaryEmail::class );

		$manager = $this->createMock( MailManager::class );
		$manager
			->expects( static::once() )
			->method( 'getEmailFromAddress' )
			->with( 'old@test.com', $user )
			->willReturn( $email );
		$manager
			->expects( static::never() )
			->method( 'addEmail' );
		$manager
			->expects( static::once() )
			->method( 'updateAuthenticationStatus' )
			->with( $email, $timestamp );

		$handler = new EmailHandler( $manager );
		$handler->onPrefsEmailAudit( $user, 'old@test.com', 'user@test.com' );
	}
}
