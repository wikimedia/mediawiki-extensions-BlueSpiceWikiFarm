<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\FavouriteInstances\Store;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Rest\HttpException;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\QueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;

class GetUserInstances extends QueryStore {

	/**
	 * @param HookContainer $hookContainer
	 * @param Config $farmConfig
	 * @param Config $config
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		HookContainer $hookContainer,
		private readonly Config $farmConfig,
		private readonly Config $config,
		private readonly IAccessStore $accessStore,
		private readonly InstanceStore $instanceStore,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
		parent::__construct( $hookContainer );
	}

	/**
	 * @return IStore
	 * @throws HttpException
	 */
	protected function getStore(): IStore {
		if ( !$this->farmConfig->get( 'shareUsers' ) || !$this->farmConfig->get( 'shareUserSessions' ) ) {
			// Not valid request in this kind of setup
			throw new HttpException( 'This is not enabled for this edition', 404 );
		}
		$context = RequestContext::getMain();
		return new Store( $context, $this->instanceStore, $this->farmConfig, $this->config, $this->accessStore, $this->userOptionsLookup );
	}

}
