<?php

namespace MediaWiki\Extension\MultiMail\Tests\Unit\Mail;

use MediaWiki\Extension\MultiMail\Mail\SecondaryEmail;
use MediaWiki\User\User;
use MediaWikiUnitTestCase;
use MWTimestamp;
use function md5;
use function strlen;
use function strtotime;
use function wfTimestamp;
use function wfTimestampNow;
use const TS_MW;
use const TS_UNIX;

/**
 * @covers \MediaWiki\Extension\MultiMail\Mail\SecondaryEmail
 */
class SecondaryEmailTest extends MediaWikiUnitTestCase {
	public function testAccessors(): void {
		$user = $this->createMock( User::class );
		$user->method( 'getRealName' )->willReturn( 'User real name' );
		$user->expects( static::never() )->method( 'getEmail' );
		$user->expects( static::never() )->method( 'getEmailAuthenticationTimestamp' );

		$timestamp = wfTimestampNow();

		$email = new SecondaryEmail(
			$user,
			(object)[
				'use_id' => 15,
				'use_email' => 'test@test.com',
				'use_email_authenticated' => $timestamp,
				'use_email_token' => null,
				'use_email_token_expires' => null
			],
			false,
			0
		);

		static::assertEquals( $timestamp, $email->getEmailAuthenticationTimestamp() );
		static::assertEquals( $user, $email->getUser() );
		static::assertEquals( 'User real name', $email->getRealName() );
		static::assertEquals( 15, $email->getId() );
		static::assertEquals( 'test@test.com', $email->getEmail() );
	}

	/**
	 * @dataProvider provideEmailConfirmed
	 *
	 * @param string|null $timestamp
	 * @param bool $emailAuthenticationEnabled
	 * @param bool $expected
	 */
	public function testIsEmailConfirmed( ?string $timestamp, bool $emailAuthenticationEnabled, bool $expected ): void {
		$email = new SecondaryEmail(
			$this->createNoOpMock( User::class ),
			(object)[
				'use_id' => 15,
				'use_email' => 'test@test.com',
				'use_email_authenticated' => $timestamp,
				'use_email_token' => null,
				'use_email_token_expires' => null
			],
			$emailAuthenticationEnabled,
			0
		);

		static::assertEquals( $expected, $email->isEmailConfirmed() );
	}

	/**
	 * Data provider for testIsEmailConfirmed.
	 *
	 * @return array
	 */
	public static function provideEmailConfirmed(): array {
		return [
			'EmailAuthenticationEnabled => false, with timestamp' => [
				wfTimestampNow(),
				false,
				true
			],
			'EmailAuthenticationEnabled => true, with timestamp' => [
				wfTimestampNow(),
				true,
				true
			],
			'EmailAuthenticationEnabled => false, without timestamp' => [
				null,
				false,
				true
			],
			'EmailAuthenticationEnabled => true, without timestamp' => [
				null,
				true,
				false
			]
		];
	}

	/**
	 * @dataProvider provideEmailConfirmationPending
	 *
	 * @param bool $confirmed
	 * @param string|null $emailToken
	 * @param string|null $emailTokenExpires
	 * @param bool $expected
	 */
	public function testIsEmailConfirmationPending(
		bool $confirmed,
		?string $emailToken,
		?string $emailTokenExpires,
		bool $expected
	): void {
		$email = new SecondaryEmail(
			$this->createNoOpMock( User::class ),
			(object)[
				'use_id' => 15,
				'use_email' => 'test@test.com',
				'use_email_authenticated' => $confirmed ? wfTimestampNow() : null,
				'use_email_token' => $emailToken,
				'use_email_token_expires' => $emailTokenExpires
			],
			true,
			0
		);

		static::assertEquals( $expected, $email->isEmailConfirmationPending() );
	}

	/**
	 * Data provider for testIsEmailConfirmed.
	 *
	 * @return array
	 */
	public static function provideEmailConfirmationPending(): array {
		return [
			'Confirmed, no token' => [
				true,
				null,
				null,
				false
			],
			'Confirmed, token only' => [
				true,
				'test token, please ignore',
				null,
				false
			],
			'Confirmed, token and expiry' => [
				true,
				'test token, please ignore',
				wfTimestampNow(),
				false
			],
			'Confirmed, expiry only' => [
				true,
				null,
				wfTimestampNow(),
				false
			],
			'Confirmed, outdated token' => [
				true,
				'test token, please ignore',
				wfTimestamp( TS_MW, strtotime( '3 years ago' ) ),
				false
			],
			'Unconfirmed, no token' => [
				false,
				null,
				null,
				false
			],
			'Unconfirmed, token only' => [
				false,
				'test token, please ignore',
				null,
				false
			],
			'Unconfirmed, token and expiry' => [
				false,
				'test token, please ignore',
				wfTimestamp( TS_MW, strtotime( 'next week' ) ),
				true
			],
			'Unconfirmed, expiry only' => [
				false,
				null,
				wfTimestamp( TS_MW, strtotime( 'next week' ) ),
				false
			],
			'Unconfirmed, outdated token' => [
				false,
				'test token, please ignore',
				wfTimestamp( TS_MW, strtotime( '3 years ago' ) ),
				false
			]
		];
	}

	public function testGenerateNewConfirmationToken(): void {
		$email = new SecondaryEmail(
			$this->createNoOpMock( User::class ),
			(object)[
				'use_id' => 15,
				'use_email' => 'test@test.com',
				'use_email_authenticated' => null,
				'use_email_token' => null,
				'use_email_token_expires' => null
			],
			true,
			5
		);

		$timestamp = wfTimestamp( TS_UNIX );
		MWTimestamp::setFakeTime( $timestamp );

		[ $token, $expiry, $hashedToken ] = $email->generateNewConfirmationToken();
		static::assertEquals( 32, strlen( $token ) );
		static::assertEquals( md5( $token ), $hashedToken );
		static::assertEquals( wfTimestamp( TS_MW, $timestamp + 5 ), $expiry );

		static::assertTrue( $email->isEmailConfirmationPending() );
	}
}
