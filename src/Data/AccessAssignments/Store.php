<?php

namespace BlueSpice\WikiFarm\Data\AccessAssignments;

use BlueSpice\WikiFarm\AccessControl\GroupRoleManager;
use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\InstanceEntity;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;
use Wikimedia\Rdbms\ILoadBalancer;

class Store implements IStore {

	/**
	 *
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param ILoadBalancer $lb
	 * @param GroupRoleManager $groupRoleManager
	 * @param InstanceEntity $instanceEntity
	 */
	public function __construct(
		private readonly InstanceGroupCreator $instanceGroupCreator,
		private readonly ILoadBalancer $lb,
		private readonly GroupRoleManager $groupRoleManager,
		private readonly InstanceEntity $instanceEntity
	) {
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->instanceGroupCreator, $this->lb, $this->groupRoleManager, $this->instanceEntity );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
