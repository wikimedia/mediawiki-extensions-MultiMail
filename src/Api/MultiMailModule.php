<?php

namespace MediaWiki\Extension\MultiMail\Api;

use ApiAuthManagerHelper;
use ApiBase;
use ApiMain;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Extension\MultiMail\Mail\MailManager;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\NumericDef;
use Wikimedia\ParamValidator\TypeDef\StringDef;

class MultiMailModule extends ApiBase {
	/** @var MailManager */
	private $mailManager;

	/** @var AuthManager */
	private $authManager;

	/**
	 * @codeCoverageIgnore
	 *
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param MailManager $mailManager
	 * @param AuthManager $authManager
	 */
	public function __construct(
		ApiMain $mainModule,
		string $moduleName,
		MailManager $mailManager,
		AuthManager $authManager
	) {
		parent::__construct( $mainModule, $moduleName );

		$this->mailManager = $mailManager;
		$this->authManager = $authManager;
	}

	/** @inheritDoc */
	public function execute(): void {
		if ( !$this->getUser()->isNamed() ) {
			$this->dieWithError(
				[ 'apierror-mustbeloggedin', $this->msg( 'action-multimail' )->plain() ],
				'notloggedin'
			);
		}

		$params = $this->extractRequestParams();

		if ( $params['check-authentication-status'] ) {
			$status = $this->authManager->securitySensitiveOperationStatus( 'ChangeEmail' );

			if ( $status !== AuthManager::SEC_FAIL ) {
				$this->getResult()->addValue( null, $this->getModuleName(), [ 'status' => $status ] );

				return;
			}
		}

		ApiAuthManagerHelper::newForModule( $this, $this->authManager )
			->securitySensitiveOperation( 'ChangeEmail' );

		$this->requireOnlyOneParameter( $params, 'email', 'id' );

		if ( isset( $params['email'] ) ) {
			if ( $this->getUser()->pingLimiter( 'changeemail' ) ) {
				$this->dieWithError( 'apierror-ratelimited' );
			}

			$status = $this->mailManager->addEmailAndSendConfirmationEmail(
				$params['email'],
				$this->getContext()
			);
		} else {
			$this->requireAtLeastOneParameter( $params, 'mail-action' );

			$email = $this->mailManager->getEmailFromId( $params['id'], $this->getUser() );

			if ( !$email ) {
				$this->dieWithError( 'multimail-api-no-such-email' );
			}

			if ( $params['mail-action'] === 'primary' ) {
				if ( $this->getUser()->pingLimiter( 'changeemail' ) ) {
					$this->dieWithError( 'apierror-ratelimited' );
				}

				$status = $this->mailManager->makePrimary(
					$email,
					$this->getContext()
				);
			} else {
				$status = $this->mailManager->delete( $email );
			}
		}

		if ( !$status->isGood() ) {
			$this->dieStatus( $status );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [ 'status' => 'success' ] );
	}

	/** @inheritDoc */
	public function isInternal(): bool {
		return true;
	}

	/** @inheritDoc */
	public function isWriteMode(): bool {
		return true;
	}

	/** @inheritDoc */
	public function needsToken(): string {
		return 'multimail';
	}

	/** @inheritDoc */
	public function getAllowedParams(): array {
		return [
			'email' => [
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_BYTES => 255
			],
			'id' => [
				ParamValidator::PARAM_TYPE => 'integer',
				NumericDef::PARAM_MIN => 0
			],
			'mail-action' => [
				ParamValidator::PARAM_TYPE => [
					'primary',
					'delete'
				]
			],
			'check-authentication-status' => false
		];
	}
}
