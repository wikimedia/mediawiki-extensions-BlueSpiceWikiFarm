<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MediaWiki\Message\Message;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use Wikimedia\Rdbms\ILoadBalancer;

class CopyUserOnClone extends CopyUser {

	/**
	 * @param InstanceManager $instanceManager
	 * @param ILoadBalancer $lb
	 * @param IAccessStore $accessStore
	 * @param UserFactory $userFactory
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param UserGroupManager $groupManager
	 * @param string $instanceId
	 * @param string $username
	 * @param string $sourceInstanceId
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, ILoadBalancer $lb, IAccessStore $accessStore,
		UserFactory $userFactory, InstanceGroupCreator $instanceGroupCreator, UserGroupManager $groupManager,
		string $instanceId, string $username, private readonly string $sourceInstanceId
	) {
		parent::__construct(
			$instanceManager, $lb, $accessStore, $userFactory,
			$instanceGroupCreator, $groupManager, $instanceId, $username
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	protected function assignAdminRoleToCreator(): void {
		// Assign same roles user has on this instance on target instance
		$user = $this->userFactory->newFromName( $this->username );
		if ( !$user ) {
			throw new Exception( Message::newFromKey( 'wikifarm-error-unknown' )->text() );
		}
		$sourceInstance = $this->instanceManager->getStore()->getInstanceById( $this->sourceInstanceId );
		if ( !$sourceInstance ) {
			throw new Exception( Message::newFromKey( 'wikifarm-error-unknown' )->text() );
		}
		$isAdminOnSource = $this->accessStore->userHasRoleOnInstance( $user, IAccessStore::ROLE_ADMIN, $sourceInstance );
		if ( !$isAdminOnSource ) {
			// User is not admin on source instance, so we do not need to do anything
			return;
		}
		parent::assignAdminRoleToCreator();
	}
}
