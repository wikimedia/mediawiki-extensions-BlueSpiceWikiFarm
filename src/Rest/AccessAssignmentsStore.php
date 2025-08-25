<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\Data\AccessAssignments\Store;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use Wikimedia\Rdbms\ILoadBalancer;

class AccessAssignmentsStore extends QueryStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param PermissionManager $permissionManager
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param ILoadBalancer $lb
	 * @param TeamManager $teamManager
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly PermissionManager $permissionManager,
		private readonly InstanceGroupCreator $instanceGroupCreator,
		private readonly ILoadBalancer $lb,
		private readonly TeamManager $teamManager,
		private readonly InstanceStore $instanceStore
	) {
		parent::__construct( $hookContainer );
	}

	/**
	 * @return IStore
	 * @throws HttpException
	 */
	protected function getStore(): IStore {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'userrights' ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
		$instance = $this->instanceStore->getInstanceByPath( FARMER_CALLED_INSTANCE );
		if ( !$instance ) {
			throw new HttpException( 'Instance not found', 404 );
		}
		return new Store( $this->instanceGroupCreator, $this->lb, $this->teamManager, $instance );
	}
}
