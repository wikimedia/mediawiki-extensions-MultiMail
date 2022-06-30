<?php

namespace MediaWiki\Extension\MultiMail\Hook;

use MediaWiki\Hook\PrefsEmailAuditHook;
use MediaWiki\HookContainer\HookContainer;

class HookRunner implements PrefsEmailAuditHook {
	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @codeCoverageIgnore
	 *
	 * @param HookContainer $hookContainer
	 */
	public function __construct( HookContainer $hookContainer ) {
		$this->hookContainer = $hookContainer;
	}

	/** @inheritDoc */
	public function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {
		return $this->hookContainer->run( 'PrefsEmailAudit', [ $user, $oldaddr, $newaddr ] );
	}
}
