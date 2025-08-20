<?php

namespace BlueSpice\WikiFarm\Data\WikiInstances;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store implements IStore {

	/**
	 *
	 * @var IContextSource
	 */
	protected $context = null;

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
	 *
	 * @param IContextSource $context
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 */
	public function __construct(
		IContextSource $context, InstanceStore $instanceStore, Config $farmConfig, Config $mainConfig
	) {
		$this->context = $context;
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->mainConfig = $mainConfig;
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->context, $this->instanceStore, $this->farmConfig, $this->mainConfig );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
