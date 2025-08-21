<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\HashConfig;

class DefaultConfig extends HashConfig {

	/**
	 * @param array $globals
	 */
	public function __construct( $globals ) {
		$IP = $globals['IP'];

		parent::__construct( [

			/**
			 * Where should WikiFarm save the configuration files and
			 * file repositories for each wiki instance?
			 * The directory must be readable AND writable by the server process.
			 */
			'instanceDirectory' => "$IP/_sf_instances/",

			/**
			 * Where should WikiFarm save backups in case an instance is being deleted?
			 * The directory must be readable AND writable by the server process.
			 */
			'archiveDirectory' => "$IP/_sf_archive/",

			/**
			 * The web accessible path to the file repository directory.
			 */
			'instancePath' => '/w/_sf_instances/',

			/**
			 * User credentials for the database user, which have the permissions needed,
			 * to create or delete wiki instances.
			 */
			'dbAdminUser' => $globals['wgDBuser'],
			'dbAdminPassword' => $globals['wgDBpassword'],

			/**
			 * The server name of the wiki farm
			 */
			'globalServer' => $globals['wgServer'],

			/**
			 * Common prefix for all automatically created databases.
			 */
			'dbPrefix' => 'wiki_',

			/**
			 * Should WikiFarm use a shared database for all wiki instances or create
			 * a dedicated database for each instance.
			 * For reasons of performance, each instance should have a dedicated database.
			 */
			'useSharedDB' => false,
			'sharedDBname' => $globals['wgDBname'],
			'LocalSettingsAppendPath' => "$IP/LocalSettings.BlueSpice.php",

			'basePath' => '',

			/**
			 * Location of directory with "pm-settings.php", "gm-settings.php",
			 * "nm-settings.php", ...
			 */
			'templateConfigDirectory' => dirname( __DIR__ ) . '/doc/config.template',

			/**
			 * If wiki instance name is not specified in the URL, this is what is send to the
			 * client in a 'Location' header
			 */
			'defaultRedirect' => '/w',

			/**
			 * Max number of instances that can be created. If null, there is no limit.
			 */
			'instanceLimit' => null,

			/**
			 * Connect to management database for access to instance data
			 */
			'managementDBserver' => $globals['wgDBserver'],
			'managementDBtype' => $globals['wgDBtype'],
			'managementDBname' => $globals['wgDBname'],
			'managementDBuser' => $globals['wgDBuser'],
			'managementDBpassword' => $globals['wgDBpassword'],
			'managementDBprefix' => $globals['wgDBprefix'],

			/**
			 * Share users and user sessions between instances
			 */
			'shareUsers' => false,
			'shareUserSessions' => false,
			/**
			 * Only if users are stored in a dedicated database, otherwise management DB is used
			 */
			'sharedUserDBname' => null,
			'sharedUserDBprefix' => '',

			/**
			 * Url used for inter-instance communication
			 */
			'internalServer' => $globals['wgServer'],
			/**
			 * Set dynamically to all available active instances
			 */
			'interwikiLinks' => [],

			/**
			 * Should shared search look into main instance
			 */
			'searchInMainInstance' => true,

			/**
			 * Set dinamically to all available active instances
			 */
			'searchTargets' => [],

			/**
			 * If true, user permission assignments will be done globally and automatically
			 * Not compatible with BlueSpicePermissionManager!
			 */
			'useGlobalAccessControl' => false,

			/**
			 * If true, search will return results from all accessible instances
			 * Not compatible with BlueSpiceInterwikiSearch!
			 */
			'useUnifiedSearch' => false,

			/**
			 * If true, the wiki farm will use a shared instance for files and templates
			 */
			'useSharedResources' => false,

			/**
			 * Path to the shared resources wiki
			 */
			'sharedResourcesWikiPath' => '-shared'
		] );
	}
}
