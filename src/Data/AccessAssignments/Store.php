<?php

namespace BlueSpice\WikiFarm\Data\AccessAssignments;

use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\InstanceEntity;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;
use Wikimedia\Rdbms\ILoadBalancer;

class Store implements IStore {

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
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->instanceGroupCreator, $this->lb, $this->teamManager, $this->instanceEntity );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
