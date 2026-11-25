<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\GroupRoleManager;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\AccessControl\WikiAccessLookup;
use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MediaWiki\Message\Message;
use Wikimedia\Rdbms\ILoadBalancer;

class CloneAccess extends InstanceAwareStep {

	/** @var string */
	protected $username;

	/** @var ILoadBalancer */
	protected $lb;

	/**
	 * @param InstanceManager $instanceManager
	 * @param ILoadBalancer $lb
	 * @param IAccessStore $accessStore
	 * @param GroupRoleManager $groupRoleManager
	 * @param WikiAccessLookup $wikiAccessLookup
	 * @param string $instanceId
	 * @param string $username
	 * @param string $sourceInstanceId
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, ILoadBalancer $lb,
		private readonly IAccessStore $accessStore,
		private readonly GroupRoleManager $groupRoleManager,
		private readonly WikiAccessLookup $wikiAccessLookup,
		string $instanceId, string $username, private readonly string $sourceInstanceId
	) {
		parent::__construct( $instanceManager, $instanceId );
		$this->username = $username;
		$this->lb = $lb;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		if (
			$this->instanceManager->getFarmConfig()->get( 'useGlobalAccessControl' ) &&
			$this->accessStore instanceof GroupAccessStore
		) {
			$sourceInstance = $this->instanceManager->getStore()->getInstanceById( $this->sourceInstanceId );
			if ( !$sourceInstance ) {
				throw new Exception( Message::newFromKey( 'wikifarm-error-unknown' )->text() );
			}
			$this->accessStore->cloneRoles( $sourceInstance, $this->getInstance() );
			$this->groupRoleManager->cloneRoles( $sourceInstance, $this->getInstance() );
			$this->wikiAccessLookup->cloneAccessLevel( $sourceInstance, $this->getInstance() );
		}

		return $data;
	}
}
