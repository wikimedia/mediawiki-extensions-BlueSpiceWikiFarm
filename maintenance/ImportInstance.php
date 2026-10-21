<?php

use BlueSpice\WikiFarm\Import\ImporterFactory;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MWStake\MediaWiki\Component\ProcessManager\StepExecutor;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class ImportInstance extends Maintenance {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'packageFile', 'Package name. This package must exist in _import directory', required: true, withArg: true );
	}

	public function execute() {
		if ( !$this->getConfig()?->get( 'WikiFarmEnableImportExport' ) ) {
			$this->error( 'Import/export is not enabled' );
			return;
		}

		$importerFactory = new ImporterFactory();

		$executor = new StepExecutor(
			$this->getServiceContainer()->getObjectFactory()
		);

		$steps = $importerFactory->getSteps( $this->getOption( 'packageFile' ) );
		$data = $executor->execute( $steps );

		$createdInstanceId = $data['createdInstanceId'] ?? null;
		if ( !$createdInstanceId ) {
			$this->fatalError( 'Failed to import instance' );
		}
		/** @var InstanceManager $instanceManager */
		$instanceManager = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		/** @var \BlueSpice\WikiFarm\InstanceEntity $instance */
		$instance = $instanceManager
			->getStore()
			->getInstanceById( $createdInstanceId );
		if ( !$instance ) {
			$this->fatalError( 'Failed to retrieve created instance' );
		}
		if ( !$instance->isActive() ) {
			$this->fatalError( 'Imported instance is not active' );
		}
		$this->output( "Imported instance: {$instance->getPath()}\n" );
		$this->output( "URL: {$instance->getUrl( $instanceManager->getFarmConfig() )}\n\n" );

		$this->output( json_encode( $data, JSON_PRETTY_PRINT ) );
	}
}

$maintClass = 'ImportInstance';
require_once RUN_MAINTENANCE_IF_MAIN;
