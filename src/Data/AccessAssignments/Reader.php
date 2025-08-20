<?php

namespace BlueSpice\WikiFarm\Data\AccessAssignments;

use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\InstanceEntity;
use Wikimedia\Rdbms\ILoadBalancer;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 *
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param ILoadBalancer $lb
	 * @param TeamManager $teamManager
	 * @param InstanceEntity $instanceEntity
	 */
	public function __construct(
		private readonly InstanceGroupCreator $instanceGroupCreator,
		private readonly ILoadBalancer $lb,
		private readonly TeamManager $teamManager,
		private readonly InstanceEntity $instanceEntity
	) {
		parent::__construct();
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->instanceGroupCreator, $this->lb, $this->teamManager, $this->instanceEntity );
	}

	protected function makeSecondaryDataProvider() {
		return null;
	}

	/**
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}
}
