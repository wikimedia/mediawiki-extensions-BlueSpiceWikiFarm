<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\Reader as WikiInstancesReader;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Util\InstanceDisplayRecordHelper;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\DataStore\Sorter;

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
	 * Sorting by "is_system" always takes precedence, so system wikis stay on top
	 * regardless of the order the client sends its sorters in
	 *
	 * @param ReaderParams $params
	 * @return Sorter
	 */
	protected function makeSorter( $params ) {
		$systemSorts = [];
		$otherSorts = [];
		foreach ( $params->getSort() as $sort ) {
			if ( $sort->getProperty() === InstanceDisplayRecord::IS_SYSTEM ) {
				$systemSorts[] = $sort;
			} else {
				$otherSorts[] = $sort;
			}
		}
		return new Sorter( array_merge( $systemSorts, $otherSorts ) );
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
