<?php

use BlueSpice\WikiFarm\Export\ExporterFactory;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MWStake\MediaWiki\Component\ProcessManager\StepExecutor;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class ExportInstance extends Maintenance {

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'instanceId', 'Instance to export', required: true, withArg: true );
	}

	public function execute() {
		if ( !$this->getConfig()?->get( 'WikiFarmEnableImportExport' ) ) {
			$this->error( 'Import/export is not enabled' );
			return;
		}
		/** @var InstanceManager $instanceManager */
		$instanceManager = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$exporterFactory = new ExporterFactory();
		$instance = $instanceManager->getStore()->getInstanceByAny( $this->getOption( 'instanceId' ) );
		if ( !$instance ) {
			throw new InvalidArgumentException( "Instance not found: " . $this->getOption( 'instanceId' ) );
		}

		$executor = new StepExecutor(
			$this->getServiceContainer()->getObjectFactory()
		);

		$steps = $exporterFactory->getSteps( $instance );
		$data = $executor->execute( $steps );

		if ( isset( $data['packageFile'] ) ) {
			$this->output( "Package file: {$data['packageFile']}\n" );
		}
		if ( isset( $data['mainChecksum'] ) ) {
			$this->output( "Main checksum: {$data['mainChecksum']}\n" );
		}
		if ( isset( $data['packageDownloadUrl'] ) ) {
			$this->output( "Package download URL: {$data['packageDownloadUrl']}\n" );
		}
	}
}

$maintClass = 'ExportInstance';
require_once RUN_MAINTENANCE_IF_MAIN;
