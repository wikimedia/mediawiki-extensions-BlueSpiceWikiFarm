<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\Store;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;

class InstancesHandler extends QueryStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param Config $farmConfig
	 * @param Config $config
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly Config $farmConfig,
		private readonly Config $config,
		private readonly IAccessStore $accessStore,
		private readonly InstanceStore $instanceStore,
	) {
		parent::__construct( $hookContainer );
	}

	/**
	 * @return IStore
	 */
	protected function getStore(): IStore {
		$context = RequestContext::getMain();
		return new Store( $context, $this->instanceStore, $this->farmConfig, $this->config, $this->accessStore );
	}
}
