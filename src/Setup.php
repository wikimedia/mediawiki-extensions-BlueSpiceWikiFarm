<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\GroupRoleQuery;
use BlueSpice\WikiFarm\Session\SessionCache;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Languages\Data\Names;
use MediaWiki\MediaWikiServices;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\LightweightObjectStore\ExpirationAwareness;
use Wikimedia\Rdbms\DatabaseDomain;

class Setup {

	public const LAST_VISITED_STASH_KEY = 'wiki-farm-last-visited-list';

	private const INSTANCE_BADGE_COLORS = [
		// Blues
		[ 'background' => '#3C72A0', 'lightText' => true ],
		[ 'background' => '#3364AD', 'lightText' => true ],
		[ 'background' => '#4963B0', 'lightText' => true ],

		// Purples / Pinks

		[ 'background' => '#7E53A0', 'lightText' => true ],
		[ 'background' => '#AD3B67', 'lightText' => true ],
		[ 'background' => '#B82F63', 'lightText' => true ],

		// Reds

		[ 'background' => '#B7373F', 'lightText' => true ],

		// Oranges / Yellow

		[ 'background' => '#D96B1F', 'lightText' => false ],
		[ 'background' => '#E59A2F', 'lightText' => false ],
		[ 'background' => '#D6BD1A', 'lightText' => false ],

		// Greens

		[ 'background' => '#366F3C', 'lightText' => true ],
		[ 'background' => '#1F705A', 'lightText' => true ],
		[ 'background' => '#266D6A', 'lightText' => true ],

		// Cyan

		[ 'background' => '#246B82', 'lightText' => true ],

		// Neutral

		[ 'background' => '#5D666E', 'lightText' => true ],
	];

	/**
	 * @return string[]
	 */
	public static function getInstanceBadgeColors(): array {
		return self::INSTANCE_BADGE_COLORS;
	}

	public static function onRegistration() {
		if ( defined( 'MW_QUIBBLE_CI' ) ) {
			define( 'FARMER_CALLED_INSTANCE', '' );
			$GLOBALS['wgWikiFarmConfigInternal'] = new SetupCIConfig();
			return;
		}

		define( 'FARMER_ROOT', $GLOBALS['IP'] );
		define( 'FARMER_DIR', dirname( __DIR__ ) );
		$GLOBALS['mwsgFormEngineElementModules'][] = 'ext.bluespice.wikiFarm.formElements';

		static::setupInterwikiLinks();
	}

	/**
	 * Compile config to be set to the client
	 *
	 * @return array
	 */
	public static function getClientConfig() {
		$services = MediaWikiServices::getInstance();
		$config = $services->getService( 'BlueSpiceWikiFarm._Config' );
		/** @var InstanceStore $store */
		$store = $services->getService( 'BlueSpiceWikiFarm.InstanceStore' );
		$currentInstance = $store->getInstanceByPath( FARMER_CALLED_INSTANCE );
		$data = [
			'instanceId' => FARMER_CALLED_INSTANCE,
			'instanceName' => FARMER_CALLED_INSTANCE,
			'useUnifiedSearch' => $config->get( 'useUnifiedSearch' ),
			'useGlobalAccessControl' => $config->get( 'useGlobalAccessControl' ),
			'shareUsers' => $config->get( 'shareUsers' ),
			'shareUserSessions' => $config->get( 'shareUserSessions' ),
			'instanceBadgeColors' => self::INSTANCE_BADGE_COLORS,
			'instanceInterwiki' => $currentInstance?->getInterwiki(),
			'instanceDisplayName' => ''
		];
		// in root wiki we cannot accessName since its too early to get Message
		if ( defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && !FARMER_IS_ROOT_WIKI_CALL ) {
			$data['instanceDisplayName'] = $currentInstance?->getDisplayName();
		}

		if ( $config->get( 'useSharedResources' ) ) {
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
			$globalServer = $GLOBALS['wgWikiFarmConfigInternal']->get( 'globalServer' );
			$basePath = $GLOBALS['wgWikiFarmConfigInternal']->get( 'basePath' );
			$basePath = trim( $basePath, '/' );
			if ( $basePath ) {
				$basePath = '/' . $basePath . '/';
			} else {
				$basePath = '/';
			}
			$GLOBALS['wgWikiFarmConfig_interwikiLinks']['w'] = [
				'iw_prefix' => 'w',
				'iw_url' => rtrim( $globalServer, '/' ) . $basePath . 'wiki/$1',
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
			$iwPrefix = $instance->getInterwiki();
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

		$sharedTables = [
			'actor',
			'user',
			'user_autocreate_serial',
			'user_groups',
			'block',
			'block_target',
			'wiki_team_roles',
			'wiki_access_levels',
			'user_properties',
			'uto_usertasks',
		];

		if ( $connection->tableExists( 'wikifarm_session_cache', __METHOD__ ) ) {
			$sharedTables[] = 'wikifarm_session_cache';
		}
		$connection->close( __METHOD__ );

		$GLOBALS['wgSharedTables'] = array_merge( $GLOBALS['wgSharedTables'] ?? [], $sharedTables );
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

	/**
	 * @return void
	 */
	public static function filterOutRestrictedGroupsAndUsers(): void {
		if ( MW_ENTRY_POINT !== 'rest' ) {
			return;
		}
		$path = RequestContext::getMain()->getRequest()->getText( 'title' );
		$instance = FARMER_CALLED_INSTANCE === 'w' ?
			new RootInstanceEntity() :
			FARMER_CALLED_INSTANCE_OBJECT;
		if (
			in_array( $path, [
				'bluespice/farm/v1/access/assignable/users',
				'bluespice/farm/v1/access/assignable/groups'
			] )
		) {
			// Unrestricted stores
			return;
		}

		if (
			$instance instanceof RootInstanceEntity &&
			in_array( $path, [
				'bs-usermanager/v1/user-store',
				'bs-usermanager/v1/group-store'
			] )
		) {
			// Unrestricted stores
			return;
		}

		$ocf = MediaWikiServices::getInstance()->getObjectCacheFactory();
		$cache = $ocf->getLocalServerInstance();
		$key = $cache->makeKey(
			'BlueSpiceGalaxyDistributionConnector', 'filterOutRestrictedGroups', FARMER_CALLED_INSTANCE
		);

		$data = $cache->getWithSetCallback( $key, ExpirationAwareness::TTL_MINUTE,
			static function () use ( $instance ) {
				/** @var GroupAccessStore $service */
				$accessStore = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.AccessControlStore' );
				if ( !( $accessStore instanceof GroupAccessStore ) ) {
					return null;
				}
				/** @var GroupRoleQuery $groupRoleQuery */
				$groupRoleQuery = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.GroupRoleManager' );

				$roles = array_merge( [ 'reader' ], $accessStore->getHigherRoles( 'reader' ) );

				// Groups that can read this wiki - custom groups - not system wiki_*_* groups
				$groupsWithRoles = $groupRoleQuery->getGroupsWithRoles( $roles, $instance );

				$utilityFactory = MediaWikiServices::getInstance()->getService( 'MWStakeCommonUtilsFactory' );
				$all = $utilityFactory->getGroupHelper()->getAvailableGroups( [ 'filter' => [ 'custom' ] ] );
				$excludedUsers = $accessStore->getExcludedUsers( $instance, $groupsWithRoles );

				return [
					'excludedGroups' => array_diff( $all, $groupsWithRoles ),
					'excludedUsers' => $excludedUsers,
				];
			}
		);
		if ( !is_array( $data ) ) {
			return;
		}

		$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups'] =
			$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups'] ?? [];
		$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'] =
			$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups'] ?? [];

		$data['excludedGroups'] = array_diff(
			$data['excludedGroups'],
			$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups']
		);
		RequestContext::getMain()->getRequest()->getSession()->set(
			'defaultExcludedGroups', $GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups']
		);
		RequestContext::getMain()->getRequest()->getSession()->set(
			'defaultExcludedUsers', $GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeUsers']
		);

		$GLOBALS['wgWikiFarmConfig_instanceRestrictedGroups'] = array_diff(
			$data['excludedGroups'],
			$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups'],
			$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeGroups']
		);

		// Filter out any group that cannot read this wiki
		$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups'] = array_merge(
			$GLOBALS['mwsgCommonWebAPIsComponentGroupStoreExcludeGroups'],
			$data['excludedGroups'],
			[ 'bureaucrat', 'sysop', 'bot', 'autoconfirmed', 'interface-admin' ]
		);

		$data['excludedUsers'] = array_diff(
			$data['excludedUsers'],
			$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeUsers']
		);

		$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeUsers'] = array_merge(
			$GLOBALS['mwsgCommonWebAPIsComponentUserStoreExcludeUsers'],
			array_values( $data['excludedUsers'] )
		);

		$GLOBALS['wgWikiFarmConfig_instanceRestrictedUsers'] = array_values( $data['excludedUsers'] );
	}
}
