<?php

use BlueSpice\WikiFarm\InstanceVaultHelper;
use BlueSpice\WikiFarm\SettingsReader;
use BlueSpice\WikiFarm\SyncUserTableFactory;
use MediaWiki\Config\Config;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

/**
 * This script is used to "sync" specific user tables in some wiki instances to one database.
 * It is helpful specifically in case with "shared users" setup.
 *
 * Let's imagine that there is a BlueSpice farm, where each instance has different set of users,
 * and at some point it is decided to go with "shared users" setup for that farm.
 * Initially it is not possible because, for example, in "Instance 1"
 * there is "User1" in "users" table with "user_id=1",
 * and "Instance2" has "User2" in "users" table with "user_id=1".
 * Therefore, there will be a conflict.
 *
 * That's the case when this script can be used.
 * It'll go through all active farm instances, collect all existing unique users in one data set,
 * and import them in specified "shared database".
 * After that script will find usages of each old user/actor ID in related tables, and replace them with new value.
 *
 * ATTENTION!
 * Is it strongly recommended to turn wiki "read only" mode before execution of this script.
 * It can be done by adding this line to the "LocalSettings.php" of the root wiki:
 * <code>
 *     $wgReadOnly = true
 * </code>
 *
 * After script execution necessary configuration for "shared DB" should be added to "LocalSettings.php" at first.
 * And only after "read only" mode should be disabled (by removing corresponding like in "LocalSettings.php").
 * Here is example of "shared DB" configuration in "LocalSettings.php":
 * <code>
 *     	$wgSharedDB = '_shared_ag';
 *
 * 		// "actor" table is usually shared by default
 * 		$wgSharedTables[] = 'user';
 * 		$wgSharedTables[] = 'user_groups';
 * 		$wgSharedTables[] = 'user_properties';
 * 		$wgSharedTables[] = 'user_former_groups';
 * 		$wgSharedTables[] = 'block_target';
 * 		$wgSharedTables[] = 'mws_user_index';
 * 		$wgSharedTables[] = 'oathauth_users';
 * </code>
 *
 * @see https://wiki.hallowelt.com/wiki/Technik/MediaWiki/Shared_sessions/User_synchronization
 */
class SyncUsers extends Maintenance {

	/**
	 * List of wiki instances to sync.
	 *
	 * @var array
	 */
	private $instancesToSync = [];

	/**
	 * List of user tables to sync in "shared database".
	 * That's quite important that at first we sync "user" table and then all other tables,
	 * because all others depend on "user".
	 *
	 * @var array
	 */
	private $tablesToSync = [
		'actor',
		'user',
		'user_groups',
		'user_former_groups',
		'user_properties',
		'block_target',
		'mws_user_index',
		'oathauth_users'
	];

	/**
	 * Database which all users will be synced in.
	 *
	 * @var string
	 */
	private $sharedDb;

	/**
	 * Does root database also need to be synced?
	 *
	 * @var bool
	 */
	private $syncRootDb = false;

	/**
	 * @var Config
	 */
	private $simpleFarmerConfig;

	/**
	 * @var InstanceVaultHelper
	 */
	private $instanceVaultHelper;

	/**
	 * @var \Wikimedia\Rdbms\IDatabase
	 */
	private $db;

	/**
	 * For each of user tables which depend on "user" table:
	 * Key is table name, value is name of the column with "user.user_id" foreign key
	 *
	 * @var array
	 */
	private $userTablesIdCols = [
		'actor' => 'actor_user',
		'user_groups' => 'ug_user',
		'user_former_groups' => 'ufg_user',
		'user_properties' => 'up_user',
		'block_target' => 'bt_user',
		'mws_user_index' => 'mui_user_id',
		'oathauth_users' => 'id'
	];

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'sync-root', 'Does root database also need to be synced?' );
		$this->addOption( 'shared-db', 'Name of shared database where all users should be synced.',
			true, true );
		$this->addOption( 'instances', 'List of wiki instances to sync, separated by comma.' .
			'By default: all wiki instances in the farm', false, true );
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		$this->init();

		$mainConfig = $this->getConfig();

		$type = $mainConfig->get( 'DBtype' );

		$params = [
			'host' => $mainConfig->get( 'DBserver' ),
			'user' => $this->simpleFarmerConfig->get( 'dbAdminUser' ),
			'password' => $this->simpleFarmerConfig->get( 'dbAdminPassword' ),
		];

		$databaseFactory = MediaWikiServices::getInstance()->getDatabaseFactory();
		$this->db = $databaseFactory->create( $type, $params );

		$databases = $this->getDatabasesToSync();

		$this->output( "Databases to sync:\n" );
		foreach ( $databases as $dbData ) {
			$this->output( "- {$dbData['name']} (prefix: {$dbData['prefix']}\n" );
		}

		$userActorMap = [];

		$allUserData = [];
		foreach ( $databases as $dbData ) {
			$this->db->selectDomain( $dbData['name'] );

			foreach ( $this->tablesToSync as $tableToSync ) {
				$res = $this->db->select(
					$dbData['prefix'] . $tableToSync,
					'*',
					'',
					__METHOD__
				);

				foreach ( $res as $row ) {
					$allUserData[$tableToSync][$dbData['name']][] = (array)$row;
				}
			}
		}

		$this->output( "\nCollected necessary data from user tables.\n\n" );

		// Now we'll work with "shared DB"
		// Actually syncing all user data in that DB
		$this->db->selectDomain( $this->sharedDb );

		$this->output( "Start syncing users into \"shared DB\"...\n\n" );

		$usersMigrated = $this->migrateUsers( $allUserData['user'] );
		// Here ensure that each user has entry in "actor"
		// table of "shared DB" after migration
		// Same with users which already exist in "shared DB" (but probably even before migration)
		$this->ensureActorEntries( $usersMigrated );

		// Make more convenient array with "old user_id -> shared user_id" mapping
		// It will be used to update related tables where "user_id" is used
		$allIdsChanged = [];
		foreach ( $usersMigrated as $userMigrationKey => $userMigrationData ) {
			$sharedUserId = $userMigrationData['shared']['user_id'];

			foreach ( $userMigrationData as $dbName => $userData ) {
				if ( $dbName === 'shared' ) {
					continue;
				}

				$oldUserId = $userData['user_id'];

				$allIdsChanged[$dbName]['user'][$oldUserId] = $sharedUserId;
			}
		}

		// As soon as users are already migrated - we do not need this data anymore
		// And we don't need to process it when migrating "user tables"
		unset( $allUserData['user'] );

		// Now, after all users and actors are synced in "shared DB",
		// migrate all other user tables (like "actor", "user_groups", "user_properties", "block_target")
		// It should be done exactly in that order, because these tables depend on "user" table
		$this->migrateUserTables( $allUserData, $allIdsChanged );

		$this->updateRelatedTables( $databases, $allIdsChanged );
	}

	/**
	 * @param array $userData
	 * @return array
	 */
	private function migrateUsers( array $userData ): array {
		$usersMigrated = [];

		// Use factory to create "table sync class" for "user" table
		$userTableSync = SyncUserTableFactory::getSyncClass( 'user' );

		foreach ( $userData as $dbName => $rows ) {
			$this->output( "- Migrating users from DB '$dbName'...\n" );

			foreach ( $rows as $userRow ) {
				$userMigrationKey = $this->getUserMigrationKey( $userRow['user_name'] );

				$oldUserId = $userRow['user_id'];
				$usersMigrated[$userMigrationKey][$dbName]['user_id'] = $oldUserId;

				$stringId = $userTableSync->getStringIdentifier( $userRow );

				// If user does not exist in "shared DB" (can be checked by case-insensitive username) - import user.
				if ( !$userTableSync->existsInDb( $this->db, $userRow ) ) {
					$this->output( "-- $stringId does not exist in shared DB - adding...\n" );

					$sharedUserId = $userTableSync->syncRow( $this->db, $userRow );

					$usersMigrated[$userMigrationKey]['shared']['user_id'] = $sharedUserId;

					if ( $oldUserId != $sharedUserId ) {
						$this->output( "--- Changed user ID from $oldUserId to $sharedUserId\n" );
					}
				} else {
					// Even if user already exists in "shared DB" -
					// we anyway should make sure that we know his ID in shared DB
					// It will be used later to update related tables with "shared ID", in case of another ID
					$sharedUserId = $userTableSync->getExistingRowId( $this->db, $userRow );

					$usersMigrated[$userMigrationKey]['shared']['user_id'] = $sharedUserId;

					$this->output( "-- $stringId already exists in shared DB, ID - $sharedUserId...\n" );
				}

				// Need this data in case of creating "actor" records in "shared DB"
				$usersMigrated[$userMigrationKey]['shared']['user_name'] = $userRow['user_name'];
			}
		}

		return $usersMigrated;
	}

	/**
	 * @param string $userName
	 * @return string
	 */
	private function getUserMigrationKey( string $userName ): string {
		// return strtolower( $userName );
		return $userName;
	}

	/**
	 * @param array $usersMigrated
	 * @return void
	 */
	private function ensureActorEntries( array $usersMigrated ): void {
		$this->output( "\nEnsuring that each migrated user has corresponding entry in 'actor'" .
			"table of shared DB...\n" );

		// Check integrity of "user" and "actor" tables in "shared DB"
		foreach ( $usersMigrated as $userMigrationKey => $userMigrationData ) {
			// Check if specific migrated user has corresponding record in "actor" table
			$sharedUserId = $userMigrationData['shared']['user_id'];
			$sharedActorId = $this->db->selectField(
				'actor',
				'actor_id',
				[
					'actor_user' => $sharedUserId
				],
				__METHOD__
			);

			if ( !$sharedActorId ) {
				$this->output( "- There is no actor record for user ID \"$sharedUserId\", inserting...\n" );

				// If "actor" record does not exist - insert it
				$this->db->insert(
					'actor',
					[
						'actor_user' => $sharedUserId,
						'actor_name' => $userMigrationData['shared']['user_name']
					],
					__METHOD__
				);
			} else {
				$this->output( "- Actor record already exists for user ID \"$sharedUserId\"\n" );
			}
		}

		$this->output( "\nNow check users which already exist in shared DB," .
			"each must have corresponding 'actor' record\n" );
		$res = $this->db->select(
			[
				'user',
				'actor'
			],
			[
				'user_id',
				'user_name',
				'actor_id',
				'actor_name'
			],
			[],
			__METHOD__,
			[],
			[
				'actor' => [
					'LEFT JOIN', 'user_id = actor_user'
				]
			]
		);
		foreach ( $res as $row ) {
			if ( $row->actor_id === null ) {
				$this->output( "- Actor for shared user \"{$row->user_name}\" ({$row->user_id}) does not exist,"
					. " adding...\n" );

				$this->db->insert(
					'actor',
					[
						'actor_user' => $row->user_id,
						'actor_name' => $row->user_name
					],
					__METHOD__
				);
			}
		}

		$this->output( "\nIntegrity of 'user' and 'actor' tables in \"shared DB\" checked!\n" );
	}

	/**
	 * @param array $allUserData
	 * @param array &$allIdsChanged
	 * @return void
	 */
	private function migrateUserTables( array $allUserData, array &$allIdsChanged ): void {
		$this->output( "\nMigrating other tables...\n" );

		foreach ( $allUserData as $tableToSync => $userTableRows ) {
			$this->output( "- Migrating '$tableToSync' table...\n" );

			$tableSyncObj = SyncUserTableFactory::getSyncClass( $tableToSync );
			foreach ( $userTableRows as $dbName => $rows ) {
				$this->output( "-- DB '$dbName'...\n" );

				foreach ( $rows as $userTableRow ) {
					$sharedUserId = null;

					$userColumnName = $this->userTablesIdCols[$tableToSync];

					$oldUserId = $userTableRow[$userColumnName];
					if ( isset( $allIdsChanged[$dbName]['user'][$oldUserId] ) ) {
						$sharedUserId = $allIdsChanged[$dbName]['user'][$oldUserId];
					} else {
						$this->output( "--- User with old ID $oldUserId does not have entry in \"shared DB\"," .
							" assume that is one of users existing in \"shared DB\" before migration.\n" );
						$this->output( "--- In that case just sync user table row as it is...\n" );
					}

					if ( $sharedUserId !== null ) {
						$userTableRow[$userColumnName] = $sharedUserId;
					}

					$stringId = $tableSyncObj->getStringIdentifier( $userTableRow );

					if ( !$tableSyncObj->existsInDb( $this->db, $userTableRow ) ) {
						$this->output( "--- $stringId does not exist in shared DB - adding...\n" );

						$sharedRowId = $tableSyncObj->syncRow( $this->db, $userTableRow );
					} else {
						$this->output( "--- $stringId already exists in shared DB...\n" );

						$sharedRowId = $tableSyncObj->getExistingRowId( $this->db, $userTableRow );
					}

					// In case if row has explicit ID (for example, "bt_id" for "block_target" table)
					// and there are any related tables - then we need to save new ID for that row in "shared DB"
					// It is needed for further fixing of foreign keys in related tables
					if ( $sharedRowId && !empty( $tableSyncObj->getRelatedTables() ) ) {
						$primaryKeyCol = $tableSyncObj->getPrimaryKey();

						if ( $primaryKeyCol ) {
							$oldRowId = $userTableRow[$primaryKeyCol];

							$allIdsChanged[$dbName][$tableToSync][$oldRowId] = $sharedRowId;
						} else {
							$this->output( "---- Unable to save old -> new ID mapping, " .
								"sync object does have data about 'primary key' column!\n" );
						}
					}
				}
			}
		}
	}

	/**
	 * @param array $dbsToCheck
	 * @param array $allIdsChanged
	 * @return void
	 */
	private function updateRelatedTables( array $dbsToCheck, array $allIdsChanged ): void {
		// Here it is better to explicitly list all related tables instead of creating objects in the loop
		$userRelatedTables = [];
		foreach ( $this->tablesToSync as $tableToSync ) {
			$tableSyncObj = SyncUserTableFactory::getSyncClass( $tableToSync );

			$relatedTables = $tableSyncObj->getRelatedTables();
			if ( !empty( $relatedTables ) ) {
				$userRelatedTables[$tableToSync] = $relatedTables;
			}
		}

		foreach ( $dbsToCheck as $dbData ) {
			$this->db->selectDomain( $dbData['name'] );
			$this->output( "\nFixing foreign keys for tables in DB '{$dbData['name']}'...\n" );

			foreach ( $userRelatedTables as $userTable => $relatedTables ) {
				if ( !isset( $allIdsChanged[$dbData['name']][$userTable] ) ) {
					// There is no data in that table, so nothing to update or sync
					continue;
				}

				$this->output( "\n- Related to '$userTable' tables:\n" );

				foreach ( $relatedTables as $tableName => $foreignKeyColumns ) {
					$updatesCount = 0;

					$this->output( "-- Table '$tableName'...\n" );

					if ( is_string( $foreignKeyColumns ) ) {
						$foreignKeyColumns = [ $foreignKeyColumns ];
					}

					// Here we need to a trick when updating "user_id" foreign keys in related tables
					// Let's assume that (for example) "revision" table holds such records:
					// rev_id | actor_id
					// 111 		4
					// 112 		22

					// And we need to change at first some "actor_id" from 4 to 22 (22 - new "actor_id" in "shared DB")
					// And then another "actor_id" from 22 to 41
					// It will look like that:
					// rev_id | actor_id
					// 111 		4 -> 22
					// 112 		22

					// rev_id | actor_id
					// 111 		22 -> 41
					// 112 		22 -> 41

					// So one more user will change (which should not be) and some data will be messed up

					// To prevent that, we will "mask" updated values by adding some "mask number" to them
					// Which is bigger than any of "actor_id" values
					// So actually maximum "actor_id" value + 1
					// And after whole process done - subtract that "mask number" from "masked" values

					// So it'll look like that (if maximum "actor_id" is, for example, 50)
					// rev_id | actor_id
					// 111 		4 -> 22 + 50 = 72
					// 112 		22

					// rev_id | actor_id
					// 111 		72
					// 112 		22 -> 41 + 50 = 91

					// Then, for each "actor_id" value which is bigger than "mask number", subtract that "mask number"
					// rev_id | actor_id
					// 111 		72 - 50 = 22
					// 112 		91 - 50 = 41

					// So, at first we need to find out that "mask number"
					// For that - check maximum value in user_id/actor_id column
					$maskNumber = 1;
					try {
						foreach ( $foreignKeyColumns as $foreignKeyColumn ) {
							$maxValue = $this->db->selectField(
								$tableName,
								"MAX($foreignKeyColumn)",
								'',
								__METHOD__
							);

							// "mask number" = column max value + 1
							if ( $maxValue > $maskNumber ) {
								$maskNumber = $maxValue + 1;
							}
						}
					} catch ( Exception $e ) {
						// That could be if table does not exist...
						$this->output( "Error: " . $e->getMessage() . "\n" );
						continue;
					}

					foreach ( $allIdsChanged[$dbData['name']][$userTable] as $oldId => $newId ) {
						$this->output( "--- Changing ID from $oldId to $newId\n" );

						if ( !$oldId ) {
							$this->output( "---- Wrong ID value!\n" );
							continue;
						}

						$updateSet = [];
						foreach ( $foreignKeyColumns as $foreignKeyColumn ) {
							$updateSet[$foreignKeyColumn] = $newId + $maskNumber;
						}

						$where = [];
						foreach ( $foreignKeyColumns as $foreignKeyColumn ) {
							$where[$foreignKeyColumn] = $oldId;
						}
						$where = $this->db->makeList( $where, Database::LIST_OR );

						$this->db->update(
							$tableName,
							$updateSet,
							$where,
							__METHOD__
						);

						$updatesCount += $this->db->affectedRows();
					}

					// Now "unmask" all changed values
					foreach ( $foreignKeyColumns as $foreignKeyColumn ) {
						$this->db->update(
							$tableName,
							[ "$foreignKeyColumn = $foreignKeyColumn - $maskNumber" ],
							"$foreignKeyColumn > $maskNumber",
							__METHOD__
						);
					}

					$this->output( "Totally: $updatesCount records updated\n" );
				}
			}
		}
	}

	/**
	 * Init necessary for sync process variables.
	 *
	 * @return void
	 * @see SyncUsers::$sharedDb
	 * @see SyncUsers::$syncRootDb
	 * @see SyncUsers::$simpleFarmerConfig
	 * @see SyncUsers::$instanceVaultHelper
	 * @see SyncUsers::$instancesToSync
	 */
	private function init(): void {
		$this->sharedDb = $this->getOption( 'shared-db' );

		$this->syncRootDb = (bool)$this->getOption( 'sync-root' );

		/** @var \BlueSpice\WikiFarm\InstanceManager $instanceManager */
		$instanceManager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$this->simpleFarmerConfig = $instanceManager->getFarmConfig();

		$path = $this->simpleFarmerConfig->get( 'instanceDirectory' );
		$this->instanceVaultHelper = new InstanceVaultHelper( $path );

		$instancesToSyncRaw = $this->getOption( 'instances' );
		$this->instancesToSync = [];
		if ( $instancesToSyncRaw ) {
			// There are specific wiki instances passed
			$instanceIds = explode( ',', $instancesToSyncRaw );
			foreach ( $instanceIds as $id ) {
				$instance = $instanceManager->getStore()->getInstanceByIdOrPath( $id );
				if ( $instance && $instance->isActive() ) {
					$this->instancesToSync[] = $instance;
				}
			}
		} else {
			/** @var \BlueSpice\WikiFarm\InstanceEntity $instance */
			foreach ( $instanceManager->getStore()->getAllInstances() as $instance ) {
				if ( !$instance->isActive() ) {
					continue;
				}
				$this->instancesToSync[] = $instance;
			}
		}
	}

	/**
	 * Gets list of databases which should be synced.
	 *
	 * @return array
	 */
	private function getDatabasesToSync(): array {
		if ( $this->syncRootDb ) {
			$this->instancesToSync[] = new \BlueSpice\WikiFarm\RootInstanceEntity();
		}
		$databases = [];
		/** @var \BlueSpice\WikiFarm\InstanceEntity $instance */
		foreach ( $this->instancesToSync as $instance ) {
			$databases[] = [ 'name' => $instance->getDbName(), 'prefix' => $instance->getDbPrefix() ];
		}

		return $databases;
	}

	public function extractDatabaseNames() {
		$databaseNames = [];
		/** @var \BlueSpice\WikiFarm\InstanceManager $instanceManager */
		$instanceManager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		/** @var \BlueSpice\WikiFarm\InstanceEntity $instance */
		foreach ( $instanceManager->getStore()->getAllInstances() as $instance ) {
			if ( !$instance->isActive() ) {
				continue;
			}
			$databaseNames[] = $instance->getDbName();
		}

		return $databaseNames;
	}

	/**
	 * Gets name of wiki root instance database.
	 *
	 * @return string
	 */
	private function getRootDbName(): string {
		$localSettings = $GLOBALS['IP'] . '/LocalSettings.php';

		$reader = new SettingsReader( $localSettings );
		$config = $reader->getConfig();

		$customLS = dirname( $localSettings ) . "/LocalSettings.custom.php";

		if ( file_exists( $customLS ) ) {
			$reader = new SettingsReader( $customLS );
			$customConfig = $reader->getConfig();
			$config = new MultiConfig( [
				$customConfig,
				$config
			] );
		}

		return $config->get( 'DBname' );
	}
}

$maintClass = 'SyncUsers';
require_once RUN_MAINTENANCE_IF_MAIN;
