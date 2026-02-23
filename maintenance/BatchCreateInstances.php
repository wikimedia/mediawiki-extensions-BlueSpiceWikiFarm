<?php

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstanceTemplateProvider;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class BatchCreateInstances extends Maintenance {

	/**
	 * @var InstanceManager
	 */
	private InstanceManager $manager;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'file', 'Input JSON file', true, true );
	}

	/**
	 * @return void
	 * @throws MaintenanceFatalError
	 */
	public function execute() {
		$file = file_get_contents( $this->getOption( 'file' ) );
		$decoded = json_decode( $file, true );
		if ( !$decoded ) {
			$this->fatalError( 'Invalid input file - not JSON' );
		}
		$this->manager = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		/** @var ProcessManager $processManager */
		$processManager = $this->getServiceContainer()->getService( 'ProcessManager' );

		foreach ( $decoded as $instanceKey => $data ) {
			if ( !is_string( $instanceKey ) || $this->manager->getStore()->nameExists( $instanceKey ) ) {
				$this->error( "Instance name $instanceKey invalid or already exists! Skipping...\n" );
				continue;
			}
			$this->output( "----------------------------------" );
			$this->output( "Processing instance: $instanceKey...\n" );
			$displayName = $data['displayName'] ?? $instanceKey;
			$lang = $data['lang'] ?? 'en';
			$metadata = isset( $data['metadata'] ) && is_array( $data['metadata'] ) ? $data['metadata'] : [];
			$config = isset( $data['config'] ) && is_array( $data['config'] ) ? $data['config'] : [];
			try {
				$template = $this->verifyTemplate( $data['template'] ?? '' );
			} catch ( Exception $exception ) {
				$this->error( 'Invalid template: ' . $exception->getMessage() . ". Skipping...\n" );
				continue;
			}
			try {
				$instanceData = $this->createInstance( $instanceKey, $displayName, [
					'lang' => $lang,
					'userName' => $data['userName'] ?? null,
					'metadata' => $metadata,
					'config' => $config,
					'template' => $template,
				] );

				$pid = $instanceData['process'];
				$this->output( "Instance URL: {$instanceData['instanceUrl']}\n" );
				$this->output( "Started process: {$pid}\n" );
				$process = $processManager->getProcessInfo( $pid );
				if ( !$process ) {
					$this->error( "Process failed to register\n" );
					continue;
				}
				$doneSteps = [];
				while ( $process->getExitCode() === null ) {
					$progress = $process->getStepProgress();
					foreach ( $progress as $step => $status ) {
						if ( $status === 'completed' ) {
							if ( !in_array( $step, $doneSteps ) ) {
								$this->output( "$step => done\n" );
								$doneSteps[] = $step;
							}
						}
					}
					sleep( 2 );
					$process = $processManager->getProcessInfo( $pid );
				}
				$this->output( "Process finished: {$process->getState()}" );
			} catch ( Throwable $ex ) {
				$this->error( "Failed to start instance creation process\n" );
			}
		}
	}

	/**
	 * @param string $instanceName
	 * @param string $displayName
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	private function createInstance( string $instanceName, string $displayName, array $options ): array {
		$options['userName'] = $options['userName'] ?? 'MediaWiki default';

		$pid = $this->manager->createInstance( $instanceName, $displayName, $options );
		if ( $pid ) {
			return [
				'process' => $pid,
				'instanceUrl' => $this->manager->getUrlForNewInstance( $instanceName )
			];
		}
		throw new Exception( 'Failed to start creation process' );
	}

	/**
	 * @param string $template
	 * @return string
	 * @throws Exception
	 */
	private function verifyTemplate( string $template ): string {
		if ( !$template ) {
			return '';
		}
		$provider = new InstanceTemplateProvider( $this->getServiceContainer()->getMainConfig() );
		$template = $provider->getTemplateSource( $template );
		if ( !file_exists( $template ) ) {
			throw new Exception( 'Template source file does not exist: ' . $template );
		}
		return $template;
	}
}

$maintClass = BatchCreateInstances::class;
require_once RUN_MAINTENANCE_IF_MAIN;
