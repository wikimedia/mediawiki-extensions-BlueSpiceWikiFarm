<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\Store as WikiInstancesStore;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Util\InstanceDisplayRecordHelper;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store extends WikiInstancesStore {

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

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->context, $this->instanceStore, $this->farmConfig, $this->mainConfig,
		$this->accessStore, $this->userOptionsLookup, $this->instanceDisplayRecordHelper );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
