<?php

namespace BlueSpice\WikiFarm\Rest;

use Config;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\UserQueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\DataStore\ResultSet;
use MWStake\MediaWiki\Component\Utils\UtilityFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class AccessAssignableUsersStore extends UserQueryStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param ILoadBalancer $lb
	 * @param UserFactory $userFactory
	 * @param LinkRenderer $linkRenderer
	 * @param TitleFactory $titleFactory
	 * @param GlobalVarConfig $mwsgConfig
	 * @param UtilityFactory $utilityFactory
	 * @param Config $farmConfig
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		HookContainer $hookContainer, ILoadBalancer $lb, UserFactory $userFactory,
		LinkRenderer $linkRenderer, TitleFactory $titleFactory, GlobalVarConfig $mwsgConfig,
		UtilityFactory $utilityFactory,
		private readonly Config $farmConfig,
		private readonly PermissionManager $permissionManager
	) {
		parent::__construct(
			$hookContainer, $lb, $userFactory, $linkRenderer, $titleFactory, $mwsgConfig, $utilityFactory
		);
	}

	protected function getResult( IStore $store, ReaderParams $readerParams ): ResultSet {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'userrights' ) ) {
			throw new \PermissionsError( 'userrights' );
		}
		// This removes limitation to only list users that can read current wiki, so user can select users to give them access
		$excludedGroups = $this->farmConfig->get( 'instanceRestrictedGroups' );
		$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'] = $GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'] ?? [];
		$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'] = array_diff(
			$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'],
			$excludedGroups
		);
		return parent::getResult( $store, $readerParams );
	}
}
