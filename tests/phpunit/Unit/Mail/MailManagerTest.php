<?php

namespace MediaWiki\Extension\MultiMail\Tests\Unit\Mail;

use CentralIdLookup;
use IContextSource;
use InvalidArgumentException;
use LogicException;
use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\Extension\MultiMail\Mail\SecondaryEmail;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Mail\IEmailer;
use MediaWikiUnitTestCase;
use Title;
use TitleFactory;
use User;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;
use function str_repeat;
use const DB_REPLICA;

/**
 * @covers \MediaWiki\Extension\MultiMail\Mail\MailManager
 */
class MailManagerTest extends MediaWikiUnitTestCase {
	private function getTitleFactory(): TitleFactory {
		$title = $this->createMock( Title::class );
		$title->method( 'getSubpage' )->willReturn( $title );

		$titleFactory = $this->createMock( TitleFactory::class );
		$titleFactory->method( 'makeTitle' )->willReturn( $title );

		return $titleFactory;
	}

	public function testGetMailDb(): void {
		$db = $this->createNoOpMock( IDatabase::class );

		$lb = $this->createMock( ILoadBalancer::class );
		$lb->method( 'getConnection' )->with( DB_REPLICA, [], 'mailDbName' )->willReturn( $db );

		$manager = new MailManager(
			$lb,
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( IEmailer::class ),
			$this->getTitleFactory(),
			$this->createNoOpMock( HookContainer::class ),
			'mailDbName',
			false,
			0
		);

		static::assertEquals( $db, $manager->getMailDb( DB_REPLICA ) );
	}

	public function testMakePrimaryMismatchingUsers(): void {
		$manager = new MailManager(
			$this->createNoOpMock( ILoadBalancer::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( IEmailer::class ),
			$this->getTitleFactory(),
			$this->createNoOpMock( HookContainer::class ),
			false,
			false,
			0
		);

		$userA = $this->createMock( User::class );
		$userA->method( 'equals' )->willReturn( false );
		$userB = $this->createMock( User::class );
		$userB->method( 'equals' )->willReturn( false );

		$email = $this->createMock( SecondaryEmail::class );
		$email->method( 'getUser' )->willReturn( $userA );

		$context = $this->createMock( IContextSource::class );
		$context->method( 'getUser' )->willReturn( $userB );

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage(
			'Given secondary email does not belong to the given user!'
		);

		$manager->makePrimary( $email, $context );
	}

	public function testConfirmWithAuthenticationDisabled(): void {
		$manager = new MailManager(
			$this->createNoOpMock( ILoadBalancer::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( IEmailer::class ),
			$this->getTitleFactory(),
			$this->createNoOpMock( HookContainer::class ),
			false,
			false,
			0
		);

		$this->expectException( LogicException::class );
		$this->expectExceptionMessage(
			'This method should not have been called, $wgEmailAuthentication is false!'
		);

		$manager->confirm( 1, str_repeat( 'f', 32 ), $this->createMock( User::class ) );
	}

	public function testConfirmWithNegativeId(): void {
		$manager = new MailManager(
			$this->createNoOpMock( ILoadBalancer::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( IEmailer::class ),
			$this->getTitleFactory(),
			$this->createNoOpMock( HookContainer::class ),
			false,
			true,
			0
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '$id must not be negative!' );

		$manager->confirm( -1, str_repeat( 'f', 32 ), $this->createMock( User::class ) );
	}

	/**
	 * @dataProvider provideInvalidTokens
	 *
	 * @param string $token
	 */
	public function testConfirmWithInvalidTokenFormat( string $token ): void {
		$manager = new MailManager(
			$this->createNoOpMock( ILoadBalancer::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( IEmailer::class ),
			$this->getTitleFactory(),
			$this->createNoOpMock( HookContainer::class ),
			false,
			true,
			0
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'Invalid token format!' );

		$manager->confirm( 1, $token, $this->createMock( User::class ) );
	}

	/**
	 * Data provider for testConfirmWithInvalidTokenFormat.
	 *
	 * @return array
	 */
	public function provideInvalidTokens(): array {
		return [
			'Empty' => [ '' ],
			'Too short' => [ 'fff' ],
			'Too long' => [ str_repeat( 'f', 64 ) ]
		];
	}

	public function testGetEmailFromIdNegativeId(): void {
		$manager = new MailManager(
			$this->createNoOpMock( ILoadBalancer::class ),
			$this->createNoOpMock( CentralIdLookup::class ),
			$this->createNoOpMock( IEmailer::class ),
			$this->getTitleFactory(),
			$this->createNoOpMock( HookContainer::class ),
			false,
			false,
			0
		);

		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( '$id must not be negative!' );

		$manager->getEmailFromId( -1, $this->createMock( User::class ) );
	}
}
