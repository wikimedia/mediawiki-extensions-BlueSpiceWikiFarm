<?php

use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class CreateInstanceShell extends Maintenance {

	/**
	 * @var InstanceManager
	 */
	private $manager;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'path', 'Instance path', true, true );
		$this->addOption( 'displayName', 'Instance display name', false, true );
		$this->addOption( 'lang', 'Instance language', false, true );
	}

	public function execute() {
		$this->manager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$instance = $this->manager->createEmptyInstance(
			$this->getOption( 'path' ),
			$this->getOption( 'displayName' ) ?? $this->getOption( 'path' ),
			[
				'lang' => $this->getOption( 'lang', 'en' ),
			]
		);
		$this->output( "Created instance shell with ID: {$instance->getId()} and path {$instance->getPath()}\n" );

		$this->output( "Database needs to be created with this name and/or prefix: \n" );
		$this->output( "DB name: {$instance->getDbName()}\n" );
		$this->output( "DB prefix: {$instance->getDbPrefix()}\n" );

		$vaultTarget = $instance->getVault( $this->manager->getFarmConfig() );
		$this->output( "Manually create instance directory: $vaultTarget, and create database\n" );
		$this->output( 'Once all ready, run `SetInstanceReady.php --instance=' . $instance->getPath() . "\n" );
	}
}

$maintClass = CreateInstanceShell::class;
require_once RUN_MAINTENANCE_IF_MAIN;
