<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\WikiFarm\Session\SessionCache;
use MediaWiki\Config\Config;
use MediaWiki\Languages\Data\Names;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Registration\ExtensionRegistry;
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

		// Init wiki farm map
		MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.WikiMap' );
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
	 * Adds ferderated search feature for all available wiki instances
	 */
	public static function setupSearchInOtherWikisConfig() {
		$farmConfig = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );
		$store = $GLOBALS['wgWikiFarmGlobalStore'];
		if ( $farmConfig->get( 'useUnifiedSearch' ) && $farmConfig->get( 'useGlobalAccessControl' ) ) {
			$GLOBALS['wgWikiFarmConfig_searchTargets'] = [];

			if ( !FARMER_IS_ROOT_WIKI_CALL && $farmConfig->get( 'searchInMainInstance' ) ) {
				$GLOBALS['wgWikiFarmConfig_searchTargets']['w'] = [
					'index_prefix' => static::getWikiId(
						$farmConfig->get( 'managementDBname' ), $farmConfig->get( 'managementDBprefix' )
					),
					'instance-name' => Message::newFromKey( 'wikifarm-main-instance-name' )->plain(),
					'instance' => new RootInstanceEntity(),
				];
			}

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

				$GLOBALS['wgWikiFarmConfig_searchTargets'][$instance->getPath()] = [
					'index_prefix' => static::getWikiId( $instance->getDbName(), $instance->getDbPrefix() ),
					'instance-name' => $instance->getDisplayName(),
					'instance' => $instance,
				];
			}
		} else {
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
		if ( MW_ENTRY_POINT !== 'index' && MW_ENTRY_POINT !== 'api' && MW_ENTRY_POINT !== 'rest' ) {
			return;
		}
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'OAuth' ) ) {
			return;
		}
		$farmConfig = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );
		$internalServer = $farmConfig->get( 'internalServer' );

		if ( !is_array( $GLOBALS['wgContentTransferTargets'] ) ) {
			$GLOBALS['wgContentTransferTargets'] = [];
		}
		if ( !is_array( $GLOBALS['bsgTranslateTransferNamespaces'] ?? [] ) ) {
			$GLOBALS['bsgTranslateTransferNamespaces'] = [];
		}
		foreach ( $GLOBALS['wgWikiFarmGlobalStore']->getAllInstances() as $instance ) {
			if ( !$instance->isActive() ) {
				continue;
			}
			$path = $instance->getPath();
			if ( static::isLanguageCode( $path ) && static::isLanguageCode( FARMER_CALLED_INSTANCE ) ) {
				$GLOBALS['bsgTranslateTransferTargets'][strtolower( $path )] = [
					'key' => $path,
					'url' => $instance->getUrl( $farmConfig ) . '/wiki'
				];

				$GLOBALS['bsgTranslateTransferNamespaces'][strtolower( $path )] = [ NS_MAIN ];
			}
			if ( $path === FARMER_CALLED_INSTANCE ) {
				// Do not setup current instance
				continue;
			}

			$accessToken = static::getInstanceAccessToken( $instance );
			if ( !$accessToken ) {
				continue;
			}

			$apiUrl = $internalServer . $instance->getScriptPath( $farmConfig ) . '/api.php';
			$GLOBALS['wgContentTransferTargets'][$instance->getPath()] = [
				'url' => $apiUrl,
				'access_token' => $accessToken,
				'pushToDraft' => false,
				'displayText' => $instance->getDisplayName(),
				'draftNamespace' => 'Draft'
			];
		}

		// Set up root wiki language as "leading language" for BlueSpiceTranslationTransfer
		$GLOBALS['bsgTranslateTransferLeadingLanguage'] = FARMER_ROOT_WIKI_LANGUAGE_CODE;
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
	 * @param InstanceEntity $instanceEntity
	 * @return string|null
	 */
	public static function getInstanceAccessToken( InstanceEntity $instanceEntity ): ?string {
		$instanceConfig = $instanceEntity->getConfig();
		if ( isset( $instanceConfig['access_token'] ) ) {
			return $instanceConfig['access_token'];
		}
		return null;
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
