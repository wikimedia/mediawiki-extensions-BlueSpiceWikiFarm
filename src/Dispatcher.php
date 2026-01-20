<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\ConfigDefinitionFactory;
use BlueSpice\Permission\RoleManager;
use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamQuery;
use BlueSpice\WikiFarm\MaintenanceScreens\MaintenancePageConstructor;
use Exception;
use ForeignAPIRepo;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Maintenance\MaintenanceRunner;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

class Dispatcher {

	/**
	 * @var DirectInstanceStore
	 */
	private $store;

	/**
	 * @var array
	 */
	private $request;

	/**
	 * @var Config
	 */
	private $config;

	/**
	 *
	 * @var InstanceEntity
	 */
	private $instance = null;

	/**
	 *
	 * @var string
	 */
	private $instanceVaultPathname = '';

	/**
	 *
	 * @param array $request $_REQUEST
	 * @param DirectInstanceStore $store
	 * @param Config $config
	 */
	public function __construct( array $request, DirectInstanceStore $store, Config $config ) {
		$this->request = $request;
		$this->store = $store;
		$this->config = $config;
	}

	/**
	 *
	 * @var string
	 */
	private $customSettingsFile = '';

	/**
	 *
	 * @var string[]
	 */
	private $filesToRequire = [];

	/**
	 * @return string[]
	 */
	public function getFilesToRequire() {
		$this->initInstance();
		$this->defineConstants();
		if ( $this->isCliInstallerContext() ) {
			return [];
		}

		if ( $this->isInstanceWikiCall() ) {
			if ( $this->instance->getStatus() !== InstanceEntity::STATUS_READY ) {
				$this->showNotReadyScreen();
			}
			if ( !( $this->instance instanceof NonExistingInstanceEntity ) ) {
				$this->initInstanceVaultPathname();
				$this->customSettingsFile = "$this->instanceVaultPathname/LocalSettings.custom.php";
				$this->setupEnvironment();
			}
		}
		$this->includeLocalSettingsAppend();
		$this->maybeIncludeLocalSettingsCustom();
		$this->setupSharedUserSessionsIfEnabled();
		$this->maybeSetupSharedResources();

		// Must be executed _after_ all calls to `wfLoadExtension/s` and `wfLoadSkin/s`
		// Must no use `\Hooks::register`, as it would initialize MediaWikiServices and
		// therefore break the DynamicSettings mechanism from BlueSpiceFoundation
		$GLOBALS['wgHooks']['SetupAfterCache'][] = function () {
			$this->onSetupAfterCache();
		};

		return $this->filesToRequire;
	}

	private function onSetupAfterCache() {
		$this->applyAdditionalDynamicConfiguration();
		if ( $this->config->get( 'shareUsers' ) && $this->config->get( 'useGlobalAccessControl' ) ) {
			$this->setupAccessGroups();
		}
	}

	private function applyAdditionalDynamicConfiguration() {
		$dynamicConfigFactories = ExtensionRegistry::getInstance()
			->getAttribute( 'BlueSpiceWikiFarmDynamicConfigurationFactories' );

		foreach ( $dynamicConfigFactories as $factoryKey => $factoryCallback ) {
			if ( !is_callable( $factoryCallback ) ) {
				throw new Exception( "Callback for '$factoryKey' not callable!" );
			}

			// Can not use `call_user_func_array` here as it has troubles with passing $GLOBALS
			$dynamicConfig = $factoryCallback( $this->instance->getPath(), $GLOBALS );

			if ( $dynamicConfig instanceof IDynamicConfiguration === false ) {
				throw new Exception(
					"Callback for '$factoryKey' returned no 'IDynamicConfiguration'!"
				);
			}

			$dynamicConfig->apply();
		}
	}

	private function initInstance() {
		if ( defined( 'MW_PHPUNIT_TEST' ) ) {
			$this->instance = new RootInstanceEntity();
			return;
		}
		if ( $this->isMaintenanceScript() ) {
			// this works for all maintenance scripts.
			// put an --sfr "WIKI_PATH_NAME" on the call and the settings
			// files of the right wiki will be included.
			//TODO: Inject like $_REQUEST

			$extractor = new CliInstanceArgExtractor( $GLOBALS['argv'] );
			$instanceIdentifier = $extractor->extractInstanceIdentifier();
			$this->instance = $this->getInstance( $instanceIdentifier );
			if (
				$this->instance &&
				!( $this->instance instanceof RootInstanceEntity ) &&
				!( $this->instance instanceof NonExistingInstanceEntity )
			) {
				if ( !$extractor->extractIsQuiet() ) {
					echo ">>> Running maintenance script for instance '{$this->instance->getDisplayName()}'\n";
				}

				// We need to reset let the maintenance script reload the arguments, as we now have
				// removed the "--sfr" flag, which would lead to an "Unexpected option" error
				// Since '--sfr' is already removed from $argv in the extractor, just re-init the runner
				/** @var MaintenanceRunner */
				$runner = $GLOBALS['runner'];
				$runner->initForClass( $GLOBALS['maintClass'], $GLOBALS['argv'] );
			}
		} else {
			$this->instance = $this->getInstance( $this->request['sfr'] ?? null );
			if ( isset( $_REQUEST['sfr'] ) ) {
				// Must be done on the super-global directly
				// since PHP 8 does not pass by reference anymore
				unset( $_REQUEST['sfr'] );
				RequestContext::getMain()->getRequest()->unsetVal( 'sfr' );
			}

			$this->redirectIfNoInstance();
		}
	}

	private function initInstanceVaultPathname() {
		$this->instanceVaultPathname = $this->instance->getVault( $this->config );
	}

	private function defineConstants() {
		$normalizedRootLang = explode( '-', $GLOBALS['wgLanguageCode'] )[0];

		// For BlueSpiceTranslationTransfer
		define( 'FARMER_ROOT_WIKI_LANGUAGE_CODE', $normalizedRootLang );

		// For "root"-wiki calls only
		if ( $this->isRootWikiCall() ) {
			define( 'FARMER_IS_ROOT_WIKI_CALL', true );
			define( 'FARMER_CALLED_INSTANCE', 'w' );
		} else {
			define( 'FARMER_IS_ROOT_WIKI_CALL', false );
			define( 'FARMER_CALLED_INSTANCE', $this->instance->getPath() );
			define( 'FARMER_CALLED_INSTANCE_VAULT', $this->instance->getVault( $this->config ) );
		}
	}

	/**
	 * @return bool
	 */
	private function isRootWikiCall(): bool {
		return !$this->instance || $this->instance instanceof RootInstanceEntity;
	}

	/**
	 * @return bool
	 */
	private function isInstanceWikiCall(): bool {
		return !$this->isRootWikiCall() && $this->instance instanceof InstanceEntity;
	}

	private function setupEnvironment() {
		$scriptPath = $this->instance->getScriptPath( $this->config );
		$GLOBALS['wgUploadPath'] = $this->instance->getVault( $this->config, true ) . '/images';
		$GLOBALS['wgUploadDirectory'] = "$this->instanceVaultPathname/images";
		$GLOBALS['wgReadOnlyFile'] = "{$GLOBALS['wgUploadDirectory']}/lock_yBgMBwiR";
		$GLOBALS['wgFileCacheDirectory'] = "{$GLOBALS['wgUploadDirectory']}/cache";
		$GLOBALS['wgDeletedDirectory'] = "{$GLOBALS['wgUploadDirectory']}/deleted";
		$GLOBALS['wgCacheDirectory'] = "$this->instanceVaultPathname/cache";
		$GLOBALS['wgArticlePath'] = "$scriptPath/wiki/$1";
		$GLOBALS['wgCookiePath'] = "/{$this->instance->getPath()}";
		$GLOBALS['wgCookiePrefix'] = "{$this->instance->getDbName()}{$this->instance->getDbPrefix()}";
		$GLOBALS['wgSitename'] = $this->instance->getDisplayName();
		$GLOBALS['wgScriptPath'] = $scriptPath;
		$GLOBALS['wgDBname'] = $this->instance->getDbName();
		$GLOBALS['wgDBprefix'] = $this->instance->getDbPrefix();
		// Will be set separately
		$GLOBALS['wgWikiFarmAccessLevel'] = null;

		foreach ( $this->instance->getConfig() as $key => $value ) {
			if ( !isset( $GLOBALS[$key] ) ) {
				// Only set registered global vars
				continue;
			}
			 $GLOBALS[$key] = $value;
		}

		// Set up BlueSpice environment
		define( 'BSROOTDIR', "{$GLOBALS['IP']}/extensions/BlueSpiceFoundation" );
		// No more config dir since 4.3, old path needed for migration purposes
		define( 'BS_LEGACY_CONFIGDIR', "{$this->instanceVaultPathname}/extensions/BlueSpiceFoundation/config" );
		define( 'BSDATADIR', "{$this->instanceVaultPathname}/extensions/BlueSpiceFoundation/data" );
		define( 'BS_DATA_DIR', "{$GLOBALS['wgUploadDirectory']}/bluespice" );
		define( 'BS_CACHE_DIR', "{$GLOBALS['wgFileCacheDirectory']}/bluespice" );
		define( 'BS_DATA_PATH', "{$GLOBALS['wgUploadPath']}/bluespice" );
	}

	private function showNotReadyScreen() {
		if ( $this->isMaintenanceScript() ) {
			if ( !$this->instance || $this->instance instanceof NonExistingInstanceEntity ) {
				echo "No such instance\n";
				die;
			}
			if ( $this->instance->getStatus() === InstanceEntity::STATUS_SUSPENDED ) {
				echo "Instance is suspended\n";
				die;
			}
			return;
		}
		mwsInitComponents();
		$config = $this->instance->getConfig();
		if ( isset( $config['wgWikiFarmConfig_maintenanceMessage'] ) ) {
			$GLOBALS['wgWikiFarmConfig_maintenanceMessage'] = $config['wgWikiFarmConfig_maintenanceMessage'];
		}
		$constructor = new MaintenancePageConstructor( MediaWikiServices::getInstance(), $this->instance );
		if ( $this->instance instanceof NonExistingInstanceEntity ) {
			// ATM, context is not setup to `w`, so we cannot use normal methods to get URL
			// This will happen in second phase
			$url = $this->config->get( 'globalServer' ) . '/wiki/Special:FarmManagement';
			$GLOBALS['wgWikiFarmConfig_farmManagementUrl'] = $url;
		}
		echo $constructor->getHtml();
		die;
	}

	private function redirectIfNoInstance() {
		if ( !$this->instance ) {
			header( 'Location: ' . $this->config->get( 'defaultRedirect' ) );
			die();
		}
	}

	private function includeLocalSettingsAppend() {
		$this->doInclude( $this->config->get( 'LocalSettingsAppendPath' ) );
	}

	private function maybeIncludeLocalSettingsCustom() {
		if ( $this->isInstanceWikiCall() && file_exists( $this->customSettingsFile ) ) {
			$this->doInclude( $this->customSettingsFile );
		}
	}

	/**
	 * @param string $pathname
	 * @return void
	 */
	private function doInclude( string $pathname ) {
		$this->filesToRequire[] = $pathname;
	}

	/**
	 * @return bool
	 */
	private function isCliInstallerContext() {
		return defined( 'MEDIAWIKI_INSTALL' );
	}

	/**
	 * @return bool
	 */
	private function isMaintenanceScript() {
		return defined( 'RUN_MAINTENANCE_IF_MAIN' ) && is_file( RUN_MAINTENANCE_IF_MAIN );
	}

	/**
	 * @return void
	 */
	private function setupSharedUserSessionsIfEnabled() {
		if ( $this->config->get( 'shareUsers' ) ) {
			Setup::setupSharedUsers( $this->config );
			if ( $this->config->get( 'shareUserSessions' ) ) {
				Setup::setupSharedUserSessions( $this->config );
			}
		}
	}

	/**
	 * Create basic access control groups for each instance, format: <wikinstancepath>_<role>
	 * @return void
	 */
	private function setupAccessGroups() {
		if ( $this->instance instanceof NonExistingInstanceEntity ) {
			return;
		}
		$instance = $this->instance;
		if ( !$instance ) {
			$instance = new RootInstanceEntity();
		}
		$db = ( new ManagementDatabaseFactory( $this->config ) )->createSharedUserDatabaseConnection();
		$teamQuery = new TeamQuery( $db );
		$groupCreator = new InstanceGroupCreator( $this->store );
		// Get groups for each role and each instance
		$instanceGroups = $groupCreator->getInstanceGroups();
		// Get groups for each team
		$teamGroups = $teamQuery->getAllTeamGroups();

		$GLOBALS['bsgGroupRoles'] = [
			'*' => [],
			'user' => [],
		];
		foreach ( $instanceGroups as $instancePath => $groups ) {
			foreach ( $groups as $groupName => $roles ) {
				// This is just here for recognizing the group
				$GLOBALS['wgGroupPermissions'][$groupName] = $GLOBALS['wgGroupPermissions'][$groupName] ?? [];
				$GLOBALS['wgGroupPermissions'][$groupName]['read'] = false;
				$GLOBALS['wgAdditionalGroups'][$groupName] = [];
				$GLOBALS['wgGroupTypes'][$groupName] = 'extension-minimal';
				if (
					$instancePath === '_global' ||
					$instancePath === $instance->getPath()
				) {
					// Assign rights only to the current instance
					$GLOBALS['bsgGroupRoles'][$groupName] = $GLOBALS['bsgGroupRoles'][$groupName] ?? [];
					foreach ( $roles as $role ) {
						$GLOBALS['bsgGroupRoles'][$groupName][$role] = true;
					}
				}
			}
		}

		// Create group per team
		foreach ( $teamGroups as $teamGroup ) {
			$GLOBALS['wgGroupPermissions'][$teamGroup] = [ 'read' => false ];
			$GLOBALS['wgAdditionalGroups'][$teamGroup] = [];
		}
		// Get roles that each team has on this instance and assign corresponding rights
		$teamRolesForCurrentInstance = $teamQuery->getTeamRoles( $instance );
		foreach ( $teamRolesForCurrentInstance as $teamData ) {
			$teamGroup = $teamQuery->getTeamGroupName( $teamData['team'] );
			$groupRoles = GroupAccessStore::ROLES[$teamData['role']] ?? [];
			foreach ( $groupRoles as $groupRole ) {
				$GLOBALS['bsgGroupRoles'][$teamGroup][$groupRole] = true;
			}

		}
		$db->close( __METHOD__ );

		// Handle * and user groups
		$accessLevel = $this->instance?->getConfig()['wgWikiFarmInitialAccessLevel'] ?? 'private';
		/** @var ConfigDefinitionFactory $cfgDfn */
		$cfgDfn = MediaWikiServices::getInstance()->getService( 'BSConfigDefinitionFactory' );
		$handler = $cfgDfn->factory( 'WikiFarmAccessLevel' );
		if ( $handler && $handler->getValue() ) {
			$accessLevel = $handler->getValue();
		}
		if ( $accessLevel === 'public' ) {
			$GLOBALS['bsgGroupRoles']['*']['reader'] = true;
			$GLOBALS['bsgGroupRoles']['*']['editor'] = true;
		} elseif ( $accessLevel === 'protected' ) {
			$GLOBALS['bsgGroupRoles']['*']['reader'] = true;
			$GLOBALS['bsgGroupRoles']['*']['editor'] = false;
			$GLOBALS['bsgGroupRoles']['user']['reader'] = true;
			$GLOBALS['bsgGroupRoles']['user']['editor'] = false;
		} else {
			$GLOBALS['bsgGroupRoles']['*']['reader'] = false;
			$GLOBALS['bsgGroupRoles']['*']['editor'] = false;
			$GLOBALS['bsgGroupRoles']['user']['reader'] = false;
			$GLOBALS['bsgGroupRoles']['user']['editor'] = false;
		}
		$GLOBALS['wgWikiFarmAccessLevel'] = $accessLevel;

		// Give native sysop rights. This is important, at least for now:
		// - sysop will be the only group assigned by default on fresh farms + on CLI installer
		// - {instance}_admin and global_admin can be removed in BSUserManager, leading to lockout
		// Also, do the same for any additional super access groups defined in config
		$superAccessGroups = $this->config->get( 'superAccessGroups' ) ?? [];
		$superAccessGroups = array_merge( $superAccessGroups, [ 'sysop' ] );
		foreach ( $superAccessGroups as $superGroup ) {
			$GLOBALS['bsgGroupRoles'][$superGroup] = $GLOBALS['bsgGroupRoles']['wiki__global_maintainer'] ?? [];
		}

		// Make sure to re-apply the permissions after setup
		/** @var RoleManager $roleManager */
		$roleManager = MediaWikiServices::getInstance()->getService( 'BSRoleManager' );
		$roleManager->applyRoles();
	}

	/**
	 * @param string|null $id
	 * @return InstanceEntity|null
	 */
	private function getInstance( ?string $id ): ?InstanceEntity {
		if ( !$id ) {
			// No instance requested
			return null;
		}
		$instance = $this->store->getInstanceByIdOrPath( $id );
		if ( $instance ) {
			// Requested instance exists
			return $instance;
		}
		$pathGenerator = new InstancePathGenerator( $this->store );
		if ( $pathGenerator->checkIfValid( $id ) ) {
			// Instance does not exist, but could exist
			return new NonExistingInstanceEntity( $id );
		}
		// Instance does not exist and could not exist
		return null;
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	private function maybeSetupSharedResources() {
		if ( !$this->config->get( 'useSharedResources' ) || !$this->instance ) {
			return;
		}
		$shared = $this->store->getInstanceByPath( $this->config->get( 'sharedResourcesWikiPath' ) );
		if ( !$shared ) {
			return;
		}
		if ( $shared->getId() === $this->instance->getId() ) {
			// Do not setup shared resources for the shared instance itself
			return;
		}

		$GLOBALS['wgForeignFileRepos'][] = [
			'class' => ForeignAPIRepo::class,
			'name' => 'farmsharedresources',
			'apibase' => $shared->getUrl( $this->config ) . '/api.php',
			'hashLevels' => 2,
		];
		$GLOBALS['wgUseSharedUploads'] = true;
		$GLOBALS['wgSharedUploadDBname'] = $shared->getDbName();
		$GLOBALS['wgSharedUploadDBprefix'] = $shared->getDbPrefix();
		$GLOBALS['wgSharedUploadDirectory'] = $shared->getVault( $this->config ) . '/images';
		$GLOBALS['wgSharedUploadPath'] = $shared->getUrl( $this->config ) . '/img_auth.php';
	}

}
