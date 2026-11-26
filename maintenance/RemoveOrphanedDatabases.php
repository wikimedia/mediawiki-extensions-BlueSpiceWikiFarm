<?php

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class RemoveOrphanedDatabases extends Maintenance {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'remove', 'Do remove databases' );
	}

	/**
	 * Gets all existing databases, then databases of all running instances.
	 * Compares these two sets and deletes orphaned databases, which are not used by any of running instances.
	 */
	public function execute() {
		if ( FARMER_CALLED_INSTANCE !== 'w' ) {
			$this->fatalError( 'This maintenance script can only be run on the main wiki' );
		}

		$mainDBName = $this->getConfig()->get( 'DBname' );
		/** @var InstanceManager $instanceManager */
		$instanceManager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$orphanedInstances = [];
		foreach ( $instanceManager->getStore()->getInstanceIds() as $instanceId ) {
			$instance = $instanceManager->getStore()->getInstanceById( $instanceId );
			if ( !$instance ) {
				$this->output( "Instance $instanceId not found\n" );
				continue;
			}
			$instanceDB = $instance->getDbName();
			$activeStates = [
				InstanceEntity::STATUS_READY,
				InstanceEntity::STATUS_MAINTENANCE,
				InstanceEntity::STATUS_SUSPENDED
			];
			if ( in_array( $instance->getStatus(), $activeStates ) ) {
				continue;
			}
			if ( $instanceDB === $mainDBName ) {
				// TODO: Support partial drops
				$this->output(
					"Instance {$instance->getDisplayName()} is inactive, but uses main database, can't drop\n"
				);
				continue;
			}
			$orphanedInstances[] = $instance;

		}

		$allDatabases = $this->getAllDatabases();

		$forRemoval = [];
		foreach ( $orphanedInstances as $instance ) {
			$instanceDB = $instance->getDbName();
			if ( !in_array( $instanceDB, $allDatabases ) ) {
				$this->error(
					"Database for instance {$instance->getDisplayName()} ({$instanceDB}) not found on server\n"
				);
				continue;
			}
			$forRemoval[] = $instance;
		}

		if ( empty( $forRemoval ) ) {
			$this->output( "\nNo orphaned databases found\n" );
			return;
		}

		$this->output( "\nOrphaned databases found:\n" );
		foreach ( $forRemoval as $instance ) {
			$this->output( $instance->getDisplayName() . ' (' . $instance->getDbName() . ")\n" );
			$this->output( "\tDatabase name: " . $instance->getDbName() . "\n" );
			$this->output( "\tState: " . $instance->getStatus() . "\n" );
			if ( $this->hasOption( 'remove' ) ) {
				$this->output( "\tRemoving database..." );
				try {
					$db = $this->getDB( DB_PRIMARY );
					$db->query( "DROP DATABASE {$instance->getDbName()}", __METHOD__ );
					$this->output( "done\n\n" );
				} catch ( Exception $e ) {
					$this->output( "failed: {$e->getMessage()}\n\n" );
				}
			} else {
				$this->output( "\n" );
			}
		}
	}

	/**
	 * @return array
	 */
	private function getAllDatabases(): array {
		$res = $this->getDB( DB_REPLICA )->query( 'SHOW DATABASES', __METHOD__ );

		$databases = [];
		if ( $res ) {
			foreach ( $res as $row ) {
				$databases[] = $row->Database;
			}
		}

		return $databases;
	}

}

$maintClass = 'RemoveOrphanedDatabases';
require_once RUN_MAINTENANCE_IF_MAIN;
