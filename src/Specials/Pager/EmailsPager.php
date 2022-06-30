<?php

namespace MediaWiki\Extension\MultiMail\Specials\Pager;

use CentralIdLookup;
use FakeResultWrapper;
use IContextSource;
use MediaWiki\MainConfigNames;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonWidget;
use SpecialPage;
use TablePager;
use Wikimedia\Rdbms\IDatabase;
use function array_unshift;
use function htmlspecialchars;
use function iterator_to_array;

class EmailsPager extends TablePager {
	/** @var CentralIdLookup */
	private $centralIdLookup;

	/** @var bool */
	private $emailAuthentication;

	/** @var int */
	private $buttonCounter;

	/**
	 * @param IContextSource $context
	 * @param CentralIdLookup $centralIdLookup
	 * @param IDatabase $mailDb
	 */
	public function __construct(
		IContextSource $context,
		CentralIdLookup $centralIdLookup,
		IDatabase $mailDb
	) {
		$this->centralIdLookup = $centralIdLookup;
		$this->mDb = $mailDb;
		$this->emailAuthentication = $context->getConfig()->get( MainConfigNames::EmailAuthentication );

		parent::__construct( $context );

		$this->buttonCounter = 1;
	}

	/** @inheritDoc */
	protected function getTableClass(): string {
		return parent::getTableClass() . ' ext-multimail-emails-pager-table';
	}

	/** @inheritDoc */
	public function getQueryInfo(): array {
		return [
			'tables' => [
				'user_secondary_email'
			],
			'fields' => [
				'use_id',
				'use_email',
				'use_email_authenticated'
			],
			'conds' => [
				'use_cuid' => $this->centralIdLookup->centralIdFromLocalUser( $this->getUser() ),
				// Primary email is added below.
				'use_email != ' . $this->mDb->addQuotes( $this->getUser()->getEmail() )
			],
			'options' => [],
			'join_conds' => []
		];
	}

	/** @inheritDoc */
	public function reallyDoQuery( $offset, $limit, $order ): FakeResultWrapper {
		$rows = iterator_to_array( parent::reallyDoQuery( $offset, $limit, $order ) );

		array_unshift( $rows, (object)[
			'use_id' => -1,
			'use_email' => $this->getUser()->getEmail(),
			'use_email_authenticated' => $this->mDb->timestampOrNull(
				$this->getUser()->getEmailAuthenticationTimestamp()
			)
		] );

		return new FakeResultWrapper( $rows );
	}

	/** @inheritDoc */
	protected function isFieldSortable( $field ): bool {
		return false;
	}

	/** @inheritDoc */
	public function formatValue( $name, $value ) {
		$isPrimary = $this->mCurrentRow->use_id === -1;
		$isAuthenticated = !$this->emailAuthentication || $this->mCurrentRow->use_email_authenticated !== null;

		if ( $name === 'use_email' ) {
			return htmlspecialchars( $value );
		}

		if ( $name === 'use_email_authenticated' ) {
			if ( !$this->emailAuthentication ) {
				$msg = $this->msg(
					$isPrimary ? 'multimail-emails-pager-primary' : 'multimail-emails-pager-no-confirmation-needed'
				);
			} elseif ( $value ) {
				$msg = $this->msg(
					$isPrimary ? 'multimail-emails-pager-primary-confirmed' : 'multimail-emails-pager-confirmed'
				)->dateTimeParams( $value );
			} else {
				$msg = $this->msg(
					$isPrimary ? 'multimail-emails-pager-primary-unconfirmed' : 'multimail-emails-pager-unconfirmed'
				);
			}

			return $msg->escaped();
		}

		if ( $name === 'use_id' ) {
			$items = [];
			$specialPageTitle = SpecialPage::getTitleFor( 'EmailAddresses' );

			if ( !$isAuthenticated ) {
				if ( $isPrimary ) {
					$title = SpecialPage::getTitleFor( 'ConfirmEmail' );
				} else {
					$title = $specialPageTitle->getSubpage( "confirm/$value" );
				}

				$items[] = new ButtonWidget( [
					'name' => 'confirm',
					'label' => $this->msg( 'multimail-emails-pager-confirm-button-label' )->plain(),
					'title' => $this->msg( 'tooltip-multimail-emails-pager-confirm-button-label' )->plain(),
					'href' => $title->getLocalURL()
				] );
			}

			$data = [
				'id' => $value,
				'address' => $this->mCurrentRow->use_email
			];

			$items[] = new ButtonWidget( [
				'name' => 'primary' . ( $isPrimary ? '' : $value ),
				'classes' => $isPrimary || !$isAuthenticated ? [] : [ 'ext-multimail-primary' ],
				'label' => $this->msg( 'multimail-emails-pager-make-primary-button-label' )->plain(),
				'title' => $this->msg( 'tooltip-multimail-emails-pager-make-primary-button-label' )->plain(),
				'disabled' => $isPrimary || !$isAuthenticated,
				'href' => $specialPageTitle->getSubpage( "primary/$value" )->getLocalURL(),
				'data' => $data + [ 'primary' => $this->getUser()->getEmail(), 'buttonId' => $this->buttonCounter++ ],
				'infusable' => true
			] );

			$items[] = new ButtonWidget( [
				'name' => 'delete' . ( $isPrimary ? '' : $value ),
				'classes' => $isPrimary ? [] : [ 'ext-multimail-delete' ],
				'label' => $this->msg( 'multimail-emails-pager-delete-label' )->plain(),
				'title' => $this->msg( 'tooltip-multimail-emails-pager-delete-label' )->plain(),
				'invisibleLabel' => true,
				'icon' => 'trash',
				'disabled' => $isPrimary,
				'href' => $specialPageTitle->getSubpage( "delete/$value" )->getLocalURL(),
				'flags' => [ 'destructive' ],
				'data' => $data + [ 'buttonId' => $this->buttonCounter++ ],
				'infusable' => true
			] );

			return new ButtonGroupWidget( [
				'items' => $items
			] );
		}

		return "Unknown field $name - cannot format";
	}

	/** @inheritDoc */
	public function getDefaultSort(): string {
		return 'use_id';
	}

	/** @inheritDoc */
	protected function getFieldNames(): array {
		return [
			'use_email' => $this->msg( 'multimail-emails-pager-email' )->plain(),
			'use_email_authenticated' => $this->msg( 'multimail-emails-pager-status' )->plain(),
			'use_id' => $this->msg( 'multimail-emails-pager-buttons' )->plain(),
		];
	}
}
