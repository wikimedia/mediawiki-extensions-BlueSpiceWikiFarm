<?php

namespace BlueSpice\WikiFarm\Api;

use BlueSpice\Api\Store as ApiStore;
use BlueSpice\WikiFarm\Data\WikiInstances\Store;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Api\ApiMain;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;

class WikiInstancesStore extends ApiStore {

	/**
	 * @var InstanceStore
	 */
	private $instanceStore;

	/**
	 * @var Config
	 */
	private $farmConfig;

	/**
	 * @var Config
	 */
	private $mainConfig;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param string $modulePrefix
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->instanceStore = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceStore' );
		$this->farmConfig = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );
		$this->mainConfig = MediaWikiServices::getInstance()->getMainConfig();
	}

	/**
	 * @return Store
	 */
	protected function makeDataStore() {
		return new Store( $this->getContext(), $this->instanceStore, $this->farmConfig, $this->mainConfig );
	}
}
