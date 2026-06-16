<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\GroupListStore;
use BlueSpice\WikiFarm\Data\UnifiedGroupStore\Store;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\GroupStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\Utils\UtilityFactory;

class AccessAssignableGroupsStore extends GroupStore {

	/** @var Store */
	private Store $groupStore;

	/**
	 * @param HookContainer $hookContainer
	 * @param UtilityFactory $utilityFactory
	 * @param GlobalVarConfig $mwsgConfig
	 * @param GroupListStore $groupListStore
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		HookContainer $hookContainer,
		UtilityFactory $utilityFactory,
		GlobalVarConfig $mwsgConfig,
		private readonly GroupListStore $groupListStore,
		private readonly PermissionManager $permissionManager
	) {
		parent::__construct( $hookContainer, $utilityFactory, $mwsgConfig );

		$this->groupStore = new Store( $utilityFactory, $mwsgConfig, $hookContainer, $this->groupListStore );
	}

	protected function getStore(): IStore {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'userrights' ) ) {
			throw new \PermissionsError( 'userrights' );
		}
		return $this->groupStore;
	}
}
