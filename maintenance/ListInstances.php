<?php

use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class ListInstances extends Maintenance {

	/**
	 * @var InstanceManager
	 */
	private $manager;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'active', 'Show only active instances' );
	}

	public function execute() {
		$this->manager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$ids = $this->manager->getStore()->getInstanceIds();
		foreach ( $ids as $id ) {
			$instance = $this->manager->getStore()->getInstanceById( $id );
			if ( !$instance ) {
				continue;
			}
			if ( $this->hasOption( 'active' ) && !$instance->isActive() ) {
				continue;
			}
			$this->output( "> {$instance->getPath()}\n" );
			$this->output( json_encode( $instance->dbSerialize(), JSON_PRETTY_PRINT ) . "\n" );

		}
	}
}

$maintClass = 'ListInstances';
require_once RUN_MAINTENANCE_IF_MAIN;
