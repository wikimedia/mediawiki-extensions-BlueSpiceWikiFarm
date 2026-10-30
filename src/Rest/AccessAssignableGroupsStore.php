<?php

namespace BlueSpice\WikiFarm\Rest;

use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\GroupStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\Utils\UtilityFactory;

class AccessAssignableGroupsStore extends GroupStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param UtilityFactory $utilityFactory
	 * @param GlobalVarConfig $mwsgConfig
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		HookContainer $hookContainer,
		UtilityFactory $utilityFactory,
		GlobalVarConfig $mwsgConfig,
		private readonly PermissionManager $permissionManager
	) {
		parent::__construct( $hookContainer, $utilityFactory, $mwsgConfig );
	}

	protected function getStore(): IStore {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'userrights' ) ) {
			throw new \PermissionsError( 'userrights' );
		}
		return parent::getStore();
	}
}
