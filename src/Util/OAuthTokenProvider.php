<?php

namespace BlueSpice\WikiFarm\Util;

use BlueSpice\WikiFarm\DynamicConfig\CentralOAuthAccessToken;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MWStake\MediaWiki\Component\DynamicConfig\DynamicConfigManager;

class OAuthTokenProvider {

	/**
	 * @param Config $mainConfig
	 * @param DynamicConfigManager $dynamicConfigManager
	 */
	public function __construct(
		private readonly Config $mainConfig,
		private readonly DynamicConfigManager $dynamicConfigManager
	) {
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string|null
	 */
	public function getTokenForInstance( InstanceEntity $instance ): ?string {
		if ( $instance instanceof RootInstanceEntity ) {
			return $this->getRootInstanceToken();
		}
		$instanceSpecificToken = $this->getInstanceToken( $instance );
		if ( $instanceSpecificToken ) {
			return $instanceSpecificToken;
		}
		if ( $this->mainConfig->get( 'MWOAuthCentralWiki' ) ) {
			return $this->getRootInstanceToken();
		}
		return null;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string|null
	 */
	private function getInstanceToken( InstanceEntity $instance ): ?string {
		$instanceConfig = $instance->getConfig();
		if ( isset( $instanceConfig['access_token'] ) ) {
			return $instanceConfig['access_token'];
		}
		return null;
	}

	/**
	 * @return string|null
	 */
	private function getRootInstanceToken(): ?string {
		$config = $this->dynamicConfigManager->getConfigObject( CentralOAuthAccessToken::KEY );
		if ( !$config ) {
			return null;
		}

		return CentralOAuthAccessToken::extractToken(
			$this->dynamicConfigManager->retrieveRaw( $config )
		);
	}

}
