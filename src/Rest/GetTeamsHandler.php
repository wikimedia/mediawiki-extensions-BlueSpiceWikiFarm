<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\Data\Teams\Store;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;

class GetTeamsHandler extends QueryStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param TeamManager $teamManager
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly TeamManager $teamManager,
		private readonly PermissionManager $permissionManager
	) {
		parent::__construct( $hookContainer );
	}

	/**
	 * @return IStore
	 * @throws HttpException
	 */
	protected function getStore(): IStore {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'wikiadmin' ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
		return new Store( $this->teamManager );
	}
}
