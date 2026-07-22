<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\InstanceStore;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class PopulateRoleInstancePath extends \MediaWiki\Maintenance\LoggedUpdateMaintenance {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doDBUpdates() {
		/** @var InstanceStore $instanceStore */
		$instanceStore = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceStore' );
		$instance = $instanceStore->getCurrentInstance();
		if ( !$instance ) {
			$this->output( "No instance is active, skipping\n" );
			return false;
		}
		$db = $this->getDB( DB_PRIMARY );
		$db->newUpdateQueryBuilder()
			->update( 'wiki_team_roles' )
			->where( [ 'wtr_instance' => $instance->getId() ] )
			->set( [ 'wtr_instance_path' => $instance->getPath() ] )
			->caller( __METHOD__ )
			->execute();

		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-populate-role-instance-path';
	}
}

$maintClass = PopulateRoleInstancePath::class;
require_once RUN_MAINTENANCE_IF_MAIN;
