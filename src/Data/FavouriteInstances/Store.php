<?php

namespace BlueSpice\WikiFarm\Data\FavouriteInstances;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\Store as WikiInstancesStore;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store extends WikiInstancesStore {

	/**
	 *
	 * @param IContextSource $context
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 */
	public function __construct(
		IContextSource $context, InstanceStore $instanceStore,
		Config $farmConfig, Config $mainConfig,
		private readonly IAccessStore $accesStore, private readonly UserOptionsLookup $userOptionsLookup

	) {
		parent::__construct( $context, $instanceStore, $farmConfig, $mainConfig );
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->context, $this->instanceStore, $this->farmConfig, $this->mainConfig,
		$this->accesStore, $this->userOptionsLookup );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
