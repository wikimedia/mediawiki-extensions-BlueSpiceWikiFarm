<?php

use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

/**
 * Archives an existing wiki farm instance via the ProcessManager, running the same steps as the
 * REST API (pre-deletion commands → archive → post-deletion commands).
 *
 * A "complete" instance (status ready/suspended/maintenance) is archived; an incomplete instance
 * (still initialising or installing) is purged instead, matching the REST API behaviour.
 *
 * Usage:
 *   php ArchiveInstance.php --instance=mywiki
 */
class ArchiveInstance extends Maintenance {

	/** @var InstanceManager */
	private InstanceManager $manager;

	/** @var ProcessManager */
	private ProcessManager $processManager;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Archives (or purges) an existing wiki farm instance.' );

		$this->addOption(
			'instance',
			'Path or ID of the instance to archive',
			true,
			true
		);
	}

	/**
	 * @return void
	 * @throws MaintenanceFatalError
	 */
	public function execute() {
		$services = $this->getServiceContainer();
		$this->manager = $services->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$this->processManager = $services->getService( 'ProcessManager' );

		$identifier = $this->getOption( 'instance' );
		$instance = $this->manager->getStore()->getInstanceByIdOrPath( $identifier );

		if ( !$instance ) {
			$this->fatalError( "No instance found for \"$identifier\"." );
		}

		$action = $instance->isComplete() ? 'archive' : 'purge';
		$path = $instance->getPath();
		$this->output( ucfirst( $action ) . "ing instance \"$path\" (status: {$instance->getStatus()})...\n" );

		try {
			if ( $instance->isComplete() ) {
				$pid = $this->manager->archiveInstance( $instance );
			} else {
				$pid = $this->manager->purgeInstance( $instance );
			}
		} catch ( Throwable $ex ) {
			$this->fatalError( "Failed to start {$action} process: " . $ex->getMessage() );
		}

		$this->output( "Process ID: $pid\n\n" );
		$this->waitForProcess( $pid, $action );
	}

	/**
	 * @param string $pid
	 * @param string $action 'archive' or 'purge'
	 * @return void
	 * @throws MaintenanceFatalError
	 */
	private function waitForProcess( string $pid, string $action = 'archive' ): void {
		$process = $this->processManager->getProcessInfo( $pid );
		if ( !$process ) {
			$this->fatalError( "Process $pid failed to register." );
		}

		$doneSteps = [];
		while ( $process->getExitCode() === null ) {
			foreach ( $process->getStepProgress() as $step => $status ) {
				if ( $status === 'completed' && !in_array( $step, $doneSteps ) ) {
					$this->output( "  ✓ $step\n" );
					$doneSteps[] = $step;
				}
			}
			sleep( 2 );
			$process = $this->processManager->getProcessInfo( $pid );
		}

		// Flush any remaining completed steps
		foreach ( $process->getStepProgress() as $step => $status ) {
			if ( $status === 'completed' && !in_array( $step, $doneSteps ) ) {
				$this->output( "  ✓ $step\n" );
			}
		}

		$state = $process->getState();
		$exitCode = $process->getExitCode();
		$pastTense = $action === 'purge' ? 'purged' : 'archived';
		if ( $exitCode === 0 ) {
			$this->output( "\nDone. Instance $pastTense successfully (state: $state).\n" );
		} else {
			$this->fatalError( "Process finished with errors (state: $state, exit code: $exitCode)." );
		}
	}
}

$maintClass = ArchiveInstance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
