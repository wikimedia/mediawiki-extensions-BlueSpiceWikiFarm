<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\Reader as WikiInstancesReader;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Util\InstanceDisplayRecordHelper;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;

class Reader extends WikiInstancesReader {

	/**
	 * @param IContextSource $context
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 * @param IAccessStore $accessStore
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param InstanceDisplayRecordHelper $instanceDisplayRecordHelper
	 */
	public function __construct(
		IContextSource $context, InstanceStore $instanceStore,
		Config $farmConfig, Config $mainConfig,
		private readonly IAccessStore $accessStore,
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly InstanceDisplayRecordHelper $instanceDisplayRecordHelper
	) {
		parent::__construct( $context, $instanceStore, $farmConfig, $mainConfig );
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->instanceStore, $this->farmConfig, $this->mainConfig,
			$this->context, $this->accessStore, $this->userOptionsLookup, $this->instanceDisplayRecordHelper );
	}

	/**
	 * @inheritDoc
	 */
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
