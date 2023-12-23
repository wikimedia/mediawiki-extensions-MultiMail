<?php

namespace MediaWiki\Extension\MultiMail\Hook;

use MediaWiki\Api\Hook\ApiQueryTokensRegisterTypesHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class SetupHandler implements
	ApiQueryTokensRegisterTypesHook,
	LoadExtensionSchemaUpdatesHook,
	LoginFormValidErrorMessagesHook
{
	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function onApiQueryTokensRegisterTypes( &$salts ): void {
		$salts += [
			'multimail' => 'multimail',
		];
	}

	/** @inheritDoc */
	public function onLoadExtensionSchemaUpdates( $updater ): void {
		$updater->addExtensionUpdateOnVirtualDomain( [
			'virtual-multimail',
			'addTable',
			'user_secondary_email',
			__DIR__ . "/../../sql/{$updater->getDB()->getType()}/tables-generated.sql",
			true
		] );
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function onLoginFormValidErrorMessages( array &$messages ): void {
		$messages[] = 'configurationdatabase-error-nologin';
	}
}
