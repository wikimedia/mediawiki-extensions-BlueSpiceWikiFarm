<?php

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class SetInstanceReady extends Maintenance {

	/**
	 * @var InstanceManager
	 */
	private $manager;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'instance', 'Instance ID or path', true, true );
	}

	public function execute() {
		$this->manager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$instance = $this->manager->getStore()->getInstanceByIdOrPath( $this->getOption( 'instance' ) );
		if ( !$instance ) {
			$this->fatalError( 'No such instance' );
		}
		if ( $instance->getStatus() === InstanceEntity::STATUS_READY ) {
			$this->output( 'Instance is already ready' );
			return;
		}
		$instance->setStatus( 'ready' );
		$this->manager->getStore()->store( $instance );
		$this->output( 'Instance is now ready' );
	}
}

$maintClass = 'SetInstanceReady';
require_once RUN_MAINTENANCE_IF_MAIN;
