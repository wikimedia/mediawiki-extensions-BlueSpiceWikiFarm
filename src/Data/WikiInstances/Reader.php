<?php

namespace BlueSpice\WikiFarm\Data\WikiInstances;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 *
	 * @var InstanceStore
	 */
	protected $instanceStore = null;

	/**
	 *
	 * @var Config
	 */
	protected $farmConfig = null;

	/**
	 *
	 * @var Config
	 */
	protected $mainConfig = null;

	/**
	 * @param IContextSource $context
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 */
	public function __construct(
		IContextSource $context, InstanceStore $instanceStore, Config $farmConfig, Config $mainConfig
	) {
		parent::__construct( $context );
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->instanceStore, $this->farmConfig, $this->mainConfig, $this->context );
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
