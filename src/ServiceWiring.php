<?php

namespace MediaWiki\Extension\MultiMail;

use MediaWiki\Extension\MultiMail\Mail\MailManager;
use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

return [
	'MultiMail.MailManager' => static function ( MediaWikiServices $services ): MailManager {
		$config = $services->getConfigFactory()->makeConfig( 'MultiMail' );
		$mainConfig = $services->getMainConfig();

		return new MailManager(
			$services->getDBLoadBalancerFactory(),
			$services->getCentralIdLookup(),
			$services->getEmailer(),
			$services->getTitleFactory(),
			$services->getHookContainer(),
			$config->get( 'MultiMailDB' ),
			$mainConfig->get( MainConfigNames::EmailAuthentication ),
			$mainConfig->get( MainConfigNames::UserEmailConfirmationTokenExpiry )
		);
	}
];
