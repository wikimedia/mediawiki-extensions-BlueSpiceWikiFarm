<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\WikiFarm\Session\SessionCache;
use MediaWiki\Config\Config;
use MediaWiki\Languages\Data\Names;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\Rdbms\DatabaseDomain;

class Setup {
	public static function onRegistration() {
		if ( defined( 'MW_QUIBBLE_CI' ) ) {
			define( 'FARMER_CALLED_INSTANCE', '' );
			$GLOBALS['wgWikiFarmConfigInternal'] = new SetupCIConfig();
			return;
		}

		define( 'FARMER_ROOT', $GLOBALS['IP'] );
		define( 'FARMER_DIR', dirname( __DIR__ ) );

		static::setupInterwikiLinks();
	}

	/**
	 * Compile config to be set to the client
	 *
	 * @return array
	 */
	public static function getClientConfig() {
		$config = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );

		$data = [
			'instanceId' => FARMER_CALLED_INSTANCE,
			'instanceName' => FARMER_CALLED_INSTANCE,
			'useUnifiedSearch' => $config->get( 'useUnifiedSearch' ),
			'useGlobalAccessControl' => $config->get( 'useGlobalAccessControl' ),
			'shareUsers' => $config->get( 'shareUsers' ),
			'shareUserSessions' => $config->get( 'shareUserSessions' ),
		];

		if ( $config->get( 'useSharedResources' ) ) {
			/** @var DirectInstanceStore $store */
			$store = $GLOBALS['wgWikiFarmGlobalStore'];
			$sharedInstance = $store->getInstanceByPath( $config->get( 'sharedResourcesWikiPath' ) );
			$sharedInstanceUrl = $sharedInstance?->getUrl( $config );

			$data['useSharedResources'] = true;
			$data['sharedWikiPath'] = $sharedInstance?->getPath();
			$data['sharedWikiApiUrl'] = $sharedInstanceUrl ? $sharedInstanceUrl . '/api.php' : null;
		}

		return $data;
	}

	/**
	 * @return void
	 */
	protected static function setupInterwikiLinks() {
		if ( !FARMER_IS_ROOT_WIKI_CALL ) {
			$GLOBALS['wgWikiFarmConfig_interwikiLinks']['w'] = [
				'iw_prefix' => 'w',
				'iw_url' => $GLOBALS['wgServer'] . '/wiki/$1',
				'iw_api' => false,
				'iw_wikiid' => $GLOBALS['wgWikiFarmConfigInternal']->get( 'rootInstanceWikiId' ),
				'iw_local' => false
			];
		}

		/** @var InstanceEntity $instance */
		foreach ( $GLOBALS['wgWikiFarmGlobalStore']->getAllInstances() as $instance ) {
			if ( !$instance->isActive() ) {
				continue;
			}
			if ( FARMER_CALLED_INSTANCE === $instance->getPath() ) {
				// Do not add interwiki link for the current instance
				continue;
			}
			$prefix = mb_strtolower( $instance->getPath() );
			$iwPrefix = "wiki-$prefix";
			$GLOBALS['wgWikiFarmConfig_interwikiLinks'][$iwPrefix] = [
				'iw_prefix' => $iwPrefix,
				'iw_url' => $instance->getUrl( $GLOBALS['wgWikiFarmConfigInternal'] ) . '/wiki/$1',
				'iw_api' => '',
				'iw_wikiid' => $instance->getWikiId(),
				'iw_local' => false
			];
		}
	}

	/**
	 * Helper method that can be registered as a callback in $wgExtensionFunctions
	 * Adds federated search feature for all available wiki instances
	 */
	public static function setupSearchInOtherWikisConfig() {
		$farmConfig = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );
		if ( $farmConfig->get( 'useUnifiedSearch' ) ) {
			return;
		}
		if ( !isset( $GLOBALS['bsgInterwikiSearchAllowMainSource'] ) ) {
			$GLOBALS['bsgInterwikiSearchAllowMainSource'] = false;
		} else {
			$GLOBALS['bsgInterwikiSearchAllowMainSource'] = true;
		}
		$internalServer = $farmConfig->get( 'internalServer' );
		if ( !empty( FARMER_CALLED_INSTANCE ) && $GLOBALS['bsgInterwikiSearchAllowMainSource'] ) {
			$GLOBALS['bsgInterwikiSearchSources']['w'] = [
				"name" => 'w',
				"api-endpoint" => "$internalServer/w/api.php",
				"search-on-wiki-url" => "{$GLOBALS['wgServer']}/w/index.php?title=Special:SearchCenter&q=$1",
				'public-wiki' => false,
				'same-domain' => true,
			];
		}
		$store = $GLOBALS['wgWikiFarmGlobalStore'];
		/** @var InstanceEntity $instance */
		foreach ( $store->getAllInstances() as $instance ) {
			if ( $instance->getPath() === FARMER_CALLED_INSTANCE ) {
				// Do not search in the current instance
				continue;
			}
			if ( !$instance->isActive() ) {
				continue;
			}

			$metadata = $instance->getMetadata();
			if ( isset( $metadata['notsearchable'] ) && $metadata['notsearchable'] ) {
				continue;
			}

			$server = MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' );
			$scriptPath = $instance->getScriptPath( $farmConfig );
			$GLOBALS['bsgInterwikiSearchSources'][$instance->getPath()] = [
				"name" => $instance->getDisplayName(),
				"api-endpoint" => $internalServer . $scriptPath . "/api.php",
				"search-on-wiki-url" => $server . $scriptPath .
					"/index.php?title=Special:SearchCenter&q=$1",
				'public-wiki' => false,
				'same-domain' => true,
			];
		}
	}

	/**
	 * @param string $dbName
	 * @param string $dbPrefix
	 * @return string
	 */
	public static function getWikiId( string $dbName, string $dbPrefix ): string {
		$domain = new DatabaseDomain( $dbName, $GLOBALS['wgDBmwschema'], $dbPrefix );
		return strtolower( WikiMap::getWikiIdFromDbDomain( $domain ) );
	}

	/**
	 * @return void
	 */
	public static function setupContentTransfer() {
		// NOOP - For B/C
	}

	/**
	 * @return void
	 */
	public static function setupTranslationLinks() {
		if ( FARMER_IS_ROOT_WIKI_CALL ) {
			return;
		}
		if ( static::isLanguageCode( FARMER_CALLED_INSTANCE ) ) {
			$GLOBALS['bsgDiscoveryLangLinksMode'] = 'translation-transfer';
		}
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public static function isLanguageCode( string $path ): bool {
		$names = Names::NAMES;
		return isset( $names[strtolower( $path )] );
	}

	/**
	 * @param Config $farmConfig
	 * @return void
	 */
	public static function setupSharedUsers( Config $farmConfig ) {
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			return;
		}
		$connection = ( new ManagementDatabaseFactory( $farmConfig ) )->createSharedUserDatabaseConnection();
		$GLOBALS['wgSharedDB'] = $connection->getDBname();
		$GLOBALS['wgSharedPrefix'] = $connection->tablePrefix();
		$GLOBALS['wgSharedTables'][] = 'actor';
		$GLOBALS['wgSharedTables'][] = 'user';
		$GLOBALS['wgSharedTables'][] = 'user_autocreate_serial';
		$GLOBALS['wgSharedTables'][] = 'user_groups';
		$GLOBALS['wgSharedTables'][] = 'block';
		$GLOBALS['wgSharedTables'][] = 'block_target';
		$GLOBALS['wgSharedTables'][] = 'wiki_teams';
		$GLOBALS['wgSharedTables'][] = 'wiki_team_roles';
		$GLOBALS['wgSharedTables'][] = 'user_properties';

		if ( $connection->tableExists( 'wikifarm_session_cache', __METHOD__ ) ) {
			$GLOBALS['wgSharedTables'][] = 'wikifarm_session_cache';
		}
		$connection->close( __METHOD__ );
	}

	/**
	 * @return void
	 */
	public static function setupSharedUserSessions() {
		$GLOBALS['wgSessionCacheType'] = 'farm-session-cache';
		$GLOBALS['wgObjectCaches']['farm-session-cache'] = [
			// Custom session cache store, so we dont need to share whole objectcache
			'class' => SessionCache::class,
			'keyspace' => 'farm-session'
		];
		$GLOBALS['wgCookiePrefix'] = 'wikifarm';
		$GLOBALS['wgCookiePath'] = '/';
	}
}
