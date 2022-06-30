<?php

namespace MediaWiki\Extension\MultiMail\Hook;

use MediaWiki\Api\Hook\ApiQueryTokensRegisterTypesHook;
use MediaWiki\Hook\LoginFormValidErrorMessagesHook;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;
use MediaWiki\MediaWikiServices;
use function defined;

class SetupHandler implements
	ApiQueryTokensRegisterTypesHook,
	LoadExtensionSchemaUpdatesHook,
	LoginFormValidErrorMessagesHook
{

	/**
	 * @var bool Indicates if the handler is run in the installer.
	 */
	private $isInstaller;

	/**
	 * For tests only!
	 *
	 * @codeCoverageIgnore
	 *
	 * @param bool $isInstaller Indicates if the handler is run in the installer.
	 */
	public function __construct( bool $isInstaller = false ) {
		$this->isInstaller = $isInstaller ?: defined( 'MEDIAWIKI_INSTALL' );
	}

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
		$db = $updater->getDB();

		if ( !$this->isInstaller ) {
			$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'MultiMail' );
			$database = $config->get( 'MultiMailDB' );

			// Don't do updates if we're running in multi-wiki environment and the database updated
			// is not the database for this extension.
			if ( $database !== false && $db->getDomainID() !== $database ) {
				return;
			}
		}

		$updater->addExtensionTable(
			'user_secondary_email',
			__DIR__ . "/../../sql/{$db->getType()}/tables-generated.sql"
		);
	}

	/**
	 * @inheritDoc
	 * @codeCoverageIgnore
	 */
	public function onLoginFormValidErrorMessages( array &$messages ): void {
		$messages[] = 'configurationdatabase-error-nologin';
	}
}
