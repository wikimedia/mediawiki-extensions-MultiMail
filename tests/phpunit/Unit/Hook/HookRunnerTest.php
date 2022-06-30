<?php

namespace MediaWiki\Extension\MultiMail\Tests\Unit\Hook;

use Generator;
use MediaWiki\Extension\MultiMail\Hook\HookRunner;
use MediaWiki\Tests\HookContainer\HookRunnerTestBase;

/**
 * @covers \MediaWiki\Extension\MultiMail\Hook\HookRunner
 */
class HookRunnerTest extends HookRunnerTestBase {
	/** @inheritDoc */
	public function provideHookRunners(): Generator {
		yield HookRunner::class => [ HookRunner::class ];
	}
}
