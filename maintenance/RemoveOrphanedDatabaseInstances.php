<?php

use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class RemoveOrphanedDatabaseInstances extends Maintenance {

	/**
	 * @var InstanceManager
	 */
	private $manager;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'dry', 'Show instances to be removed' );
	}

	public function execute() {
		$this->manager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$this->output(
			"This script will check all instance entries in database and remove those which are not active\n" .
			"It will NOT remove databases, directories or any other resources\n\n"
		);

		$found = false;
		$ids = $this->manager->getStore()->getInstanceIds();
		foreach ( $ids as $id ) {
			$instance = $this->manager->getStore()->getInstanceById( $id );
			if ( !$instance ) {
				continue;
			}
			if ( !$instance->isComplete() ) {
				$found = true;
				$this->output( "> Instance \"" . $instance->getPath() . "\" has status: {$instance->getStatus()}\n" );
				if ( !$this->hasOption( 'dry' ) ) {
					// Remove vault
					wfRecursiveRemoveDir( $instance->getVault( $this->manager->getFarmConfig() ) );
					// Remove DB entry
					$this->manager->getStore()->removeEntry( $instance );
					$this->output( "removed\n" );
				}
			}
		}
		if ( !$found ) {
			$this->output( "Found no instances to remove\n" );
		}
	}
}

$maintClass = 'RemoveOrphanedDatabaseInstances';
require_once RUN_MAINTENANCE_IF_MAIN;
