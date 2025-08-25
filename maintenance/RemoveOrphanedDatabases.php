<?php

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Config\Config;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class RemoveOrphanedDatabases extends Maintenance {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'dry', 'Just list databases to be removed' );
	}

	/**
	 * Gets all existing databases, then databases of all running instances.
	 * Compares these two sets and deletes orphaned databases, which are not used by any of running instances.
	 */
	public function execute() {
		$mainDBName = $this->getConfig()->get( 'DBname' );
		/** @var InstanceManager $instanceManager */
		$instanceManager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$active = [];
		foreach ( $instanceManager->getStore()->getInstanceIds() as $instanceId ) {
			$instance = $instanceManager->getStore()->getInstanceById( $instanceId );
			if ( !$instance ) {
				continue;
			}
			$instanceDB = $instance->getDbName();
			if ( $instanceDB === $mainDBName ) {
				continue;
			}
			if (
				$instance->getStatus() !== InstanceEntity::STATUS_READY &&
				$instance->getStatus() !== InstanceEntity::STATUS_MAINTENANCE
			) {
				continue;
			}
			$active[] = $instanceDB;
		}

		$allDatabases = $this->getAllInstanceDatabaseNames( $instanceManager->getFarmConfig() );
		$orphaned = array_diff( $allDatabases, $active );

		if ( empty( $orphaned ) ) {
			$this->output( 'No orphaned databases found' );
			return;
		}

		$this->output( "Orphaned databases found:\n" );
		foreach ( $orphaned as $dbName ) {
			$this->output( $dbName );
			if ( !$this->hasOption( 'dry' ) ) {
				$this->output( ' - dropping...' );
				$db = $this->getDB( DB_PRIMARY );
				$db->query( "DROP DATABASE $dbName", __METHOD__ );
				$this->output( " done\n" );
			} else {
				$this->output( "\n" );
			}
		}
	}

	/**
	 * @param Config $farmConfig
	 * @return array
	 */
	public function getAllInstanceDatabaseNames( Config $farmConfig ) {
		$res = $this->getDB( DB_REPLICA )->query( 'SHOW DATABASES', __METHOD__ );

		$databases = [];
		if ( $res ) {
			foreach ( $res as $row ) {
				if ( strpos( $row->Database, $farmConfig->get( 'dbPrefix' ) ) === 0 ) {
					$databases[] = $row->Database;
				}
			}
		}

		return $databases;
	}

}

$maintClass = 'RemoveOrphanedDatabases';
require_once RUN_MAINTENANCE_IF_MAIN;
