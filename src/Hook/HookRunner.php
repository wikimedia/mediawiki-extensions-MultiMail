<?php

namespace MediaWiki\Extension\MultiMail\Hook;

use MediaWiki\Hook\PrefsEmailAuditHook;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements PrefsEmailAuditHook {
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct(
		private readonly HookContainer $hookContainer,
	) {
	}

	/** @inheritDoc */
	public function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {
		return $this->hookContainer->run( 'PrefsEmailAudit', [ $user, $oldaddr, $newaddr ] );
	}
}
