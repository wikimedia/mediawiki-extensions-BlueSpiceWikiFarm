<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\GroupRoleManager;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\AccessControl\WikiAccessLookup;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Storage\InstanceTransaction;
use MediaWiki\Config\Config;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;

class PurgeInstance extends ArchiveInstance {

	public function __construct(
		InstanceManager $instanceManager, Config $mainConfig, StorageHandler $storageHandler,
		private readonly WikiAccessLookup $accessLookup,
		private readonly IAccessStore $accessStore,
		private readonly GroupRoleManager $groupRoleManager,
		string $instanceId,
	) {
		parent::__construct( $instanceManager, $mainConfig, $storageHandler, $instanceId );
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws \Exception
	 */
	public function execute( $data = [] ): array {
		$this->getInstanceManager()->getLogger()->info(
			"Purging instance {path} ...",
			[ 'path' => $this->getInstance()->getPath() ]
		);

		$this->setSharedSetupFlag();

		// Drop vault
		$backend = $this->storageHandler->getBackend(
			$this->getInstanceManager()->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		( new InstanceTransaction( $backend ) )
			->deleteInstanceDirectory( $this->getInstance()->getPath() )
			->commit();

		$this->dropInstanceDatabase();

		// Drop operational data
		$this->removeInstanceData();

		return array_merge( $data, [ 'success' => true ] );
	}

	/**
	 * @return void
	 */
	protected function removeInstanceData(): void {
		$this->accessLookup->removeAccessLevelForInstance( $this->instance );

		$farmConfig = $this->getInstanceManager()->getFarmConfig();
		if ( $farmConfig->get( 'useGlobalAccessControl' ) && $this->accessStore instanceof GroupAccessStore ) {
			$this->accessStore->deleteForInstance( $this->instance );
			$this->groupRoleManager->deleteForInstance( $this->instance );
		}
	}

	/**
	 * @return void
	 */
	protected function dropInstanceDatabase() {
		$db = $this->getInstanceManager()->getDatabaseConnectionForInstance( $this->getInstance() );
		if ( !$db ) {
			$this->getInstanceManager()->getLogger()->error( "Drop database failed: cannot get connection" );
			return;
		}
		if ( $this->sharedDBSetup ) {
			// Allow next query to return a long response
			$db->query( "SET SESSION group_concat_max_len = 1000000", __METHOD__ );
			// DROP VIEWS
			$this->executeGeneratedStatement(
				"select concat( 'drop view ', TABLE_SCHEMA, '.', TABLE_NAME, ';') as statement " .
				"from information_schema.views where table_name like '{$this->getInstance()->getDbPrefix()}%';",
				$db
			);
			// DROP TABLES
			$this->executeGeneratedStatement(
				"SELECT CONCAT( 'DROP TABLE ', GROUP_CONCAT( table_name ) , ';' ) AS statement" .
				" FROM information_schema.tables  WHERE table_name LIKE '{$this->getInstance()->getDbPrefix()}%'" .
				" AND TABLE_TYPE NOT LIKE 'VIEW'",
				$db
			);
		} else {
			try {
				$db->query( "DROP DATABASE {$this->getInstance()->getDBName()}", __METHOD__ );
			} catch ( \Throwable $e ) {
				$this->getInstanceManager()->getLogger()->error( "Drop database failed: {error}", [
					'error' => $e->getMessage()
				] );
			}
		}
	}
}
