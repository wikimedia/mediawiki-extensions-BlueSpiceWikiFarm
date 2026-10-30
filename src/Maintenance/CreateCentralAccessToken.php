<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\DynamicConfig\CentralOAuthAccessToken;
use Exception;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\Registration\ExtensionRegistry;
use MWStake\MediaWiki\Component\DynamicConfig\DynamicConfigManager;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class CreateCentralAccessToken extends CreateAccessToken {

	/**
	 * @return bool
	 * @throws Exception
	 * @throws MaintenanceFatalError
	 */
	protected function doDBUpdates() {
		$logger = LoggerFactory::getInstance( 'BlueSpiceWikiFarm' );
		$logger->debug( 'CreateCentralAccessToken: Starting' );

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'OAuth' ) ) {
			$logger->info( 'CreateCentralAccessToken: OAuth extension is not enabled' );
			$this->output( "OAuth extension is not enabled\n" );
			return false;
		}
		if ( !defined( 'WIKI_FARMING' ) ) {
			$logger->info( 'CreateCentralAccessToken: Wiki farming is not enabled' );
			$this->output( "Wiki farming is not enabled\n" );
			return false;
		}
		if ( !FARMER_IS_ROOT_WIKI_CALL ) {
			$this->output( "This script must be run on the farm root wiki, skipping\n" );
			return true;
		}

		if ( !$this->getServiceContainer()->hasService( 'MWStakeDynamicConfigManager' ) ) {
			$logger->error( 'CreateCentralAccessToken: MWStakeDynamicConfigManager service is unavailable' );
			$this->output( "MWStakeDynamicConfigManager service is unavailable\n" );
			return false;
		}

		try {
			$accessToken = $this->generateAccessToken();
			if ( !$accessToken ) {
				$logger->error( 'CreateCentralAccessToken: Failed to generate access token' );
				$this->output( "Failed to generate access token\n" );
				return false;
			}

			/** @var DynamicConfigManager $configManager */
			$configManager = $this->getServiceContainer()->getService( 'MWStakeDynamicConfigManager' );
			$config = $configManager->getConfigObject( CentralOAuthAccessToken::KEY );
			if ( !$config ) {
				$logger->error( 'CreateCentralAccessToken: DynamicConfig object not registered' );
				$this->output( "DynamicConfig object not registered\n" );
				return false;
			}

			$stored = $configManager->storeConfig( $config, [ 'access_token' => $accessToken ] );
			if ( !$stored ) {
				$logger->error( 'CreateCentralAccessToken: Failed to store centralized access token' );
				$this->output( "Failed to store centralized access token\n" );
				return false;
			}

			$logger->info( 'CreateCentralAccessToken: Centralized access token generated' );
			$this->output( "Centralized access token generated\n" );
			return true;
		} catch ( \Throwable $ex ) {
			$logger->error(
				'CreateCentralAccessToken: Failed to generate or store centralized access token: ' .
				$ex->getMessage()
			);
			$this->error( 'Failed to generate or store centralized access token: ' . $ex->getMessage() );
			return false;
		}
	}

	/**
	 * @return string
	 */
	protected function getConsumerVersion(): string {
		return '1.3';
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-create-central-access-token';
	}
}

$maintClass = CreateCentralAccessToken::class;
require_once RUN_MAINTENANCE_IF_MAIN;
