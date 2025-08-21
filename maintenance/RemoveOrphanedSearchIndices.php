<?php

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class RemoveOrphanedSearchIndices extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'BlueSpiceExtendedSearch' );
		$this->addOption( 'dry', 'Just list indices to be removed', false );
	}

	public function execute() {
		$indices = $this->getAvailableIndices();
		$this->output( "Found " . count( $indices ) . " available indices" . PHP_EOL );
		/** @var InstanceManager $instanceManager */
		$instanceManager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$activeDatabaseNames = [];
		foreach ( $instanceManager->getStore()->getInstanceIds() as $instanceId ) {
			$instance = $instanceManager->getStore()->getInstanceById( $instanceId );
			if ( !$instance ) {
				continue;
			}
			$instanceDB = $instance->getDbName();
			if (
				$instance->getStatus() !== InstanceEntity::STATUS_READY &&
				$instance->getStatus() !== InstanceEntity::STATUS_MAINTENANCE
			) {
				continue;
			}
			$activeDatabaseNames[] = $instanceDB;
		}
		$this->output( "Found " . count( $activeDatabaseNames ) . " active instances" . PHP_EOL );

		$toRemove = $this->filterIndices( $indices, $activeDatabaseNames );

		if ( !$this->getOption( 'dry' ) ) {
			$this->output( "Removing " . count( $toRemove ) . " orphaned instances" . PHP_EOL );
			foreach ( $toRemove as $index ) {
				$this->output( "Removing $index..." );
				if ( $this->removeIndex( $index ) ) {
					$this->output( "done" . PHP_EOL );
				} else {
					$this->output( "failed" . PHP_EOL );
				}
			}
		} else {
			if ( !empty( $toRemove ) ) {
				$this->output( "Would remove following indices: " . PHP_EOL );
				foreach ( $toRemove as $remove ) {
					$this->output( $remove . PHP_EOL );
				}
			} else {
				$this->output( "No indices to remove" . PHP_EOL );
			}

		}
	}

	/**
	 * @param string $index
	 * @return bool
	 */
	private function removeIndex( $index ) {
		$backend = MediaWikiServices::getInstance()->getService( 'BSExtendedSearchBackend' );
		$client = $backend->getClient();
		if ( $client->indices()->exists( [ 'index' => $index ] ) ) {
			$res = $client->indices()->delete( [ 'index' => $index ] );
			return is_array( $res ) && isset( $res['acknowledged'] ) && $res['acknowledged'];
		}
	}

	/**
	 * @return array
	 */
	private function getAvailableIndices() {
		$backend = MediaWikiServices::getInstance()->getService( 'BSExtendedSearchBackend' );
		$client = $backend->getClient();
		$response = $client->cat()->indices( [
			'format' => 'json'
		] );
		$result = [];
		foreach ( $response as $index ) {
			$name = $index['index'];
			if ( strpos( $name, 'sfr_' ) === 0 ) {
				$result[] = $name;
			}
		}

		return $result;
	}

	/**
	 * @param array $indices
	 * @param array $activeInstances
	 * @return array
	 */
	private function filterIndices( array $indices, array $activeInstances ) {
		$existing = [];
		foreach ( $indices as $index ) {
			foreach ( $activeInstances as $dbName ) {
				if ( strpos( $index, $dbName ) === 0 ) {
					$existing[] = $index;
				}
			}
		}

		return array_diff( $indices, $existing );
	}
}

$maintClass = 'RemoveOrphanedSearchIndices';
require_once RUN_MAINTENANCE_IF_MAIN;
