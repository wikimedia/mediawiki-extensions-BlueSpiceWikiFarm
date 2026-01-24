<?php

// php extensions/BlueSpiceWikiFarm/maintenance/RunForAll.php --script=maintenance/update.php --args="--quick"
// php maintenance/RunForAll.php --script=maintenance/importImages.php --args=/c/Users/rvogel/Desktop/WebDAV Test"
// php RunForAll.php --script=maintenance/rebuildLocalisationCache.php --args="--lang=de,de-formal,en --force"

use MediaWiki\Maintenance\Maintenance;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class RunForAll extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->addOption( 'script', 'The maintenance script to execute', true );
		$this->addOption( 'quiet', 'Suppress output unless there was an error' );
		$this->addOption( 'args', 'The arguments for the maintenance script', false );
		ini_set( 'error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED );
	}

	public function execute() {
		$iTotalErrors = 0;
		$sScript 	= $this->getOption( 'script' );
		$bQuiet 	= $this->hasOption( 'quiet' );
		$sArgs 		= $this->getOption( 'args', '' );

		$overallStartTime = new DateTime();
		$overallStartTimestamp = $overallStartTime->format( 'Y-m-d H:i:s' );
		if ( !$bQuiet ) {
			$this->output( "$overallStartTimestamp: Processing instances ...\n" );
		}

		/** @var \BlueSpice\WikiFarm\InstanceManager $instanceManager */
		$instanceManager = \MediaWiki\MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$store = $instanceManager->getStore();
		foreach ( $store->getInstanceIds() as $instanceId ) {
			$instance = $store->getInstanceById( $instanceId );
			if ( !$instance ) {
				continue;
			}
			if ( $instance->getStatus() !== \BlueSpice\WikiFarm\InstanceEntity::STATUS_READY ) {
				continue;
			}
			$startTime = new DateTime();
			$startTimestamp = $startTime->format( 'Y-m-d H:i:s' );
			$instancePath = $instance->getPath();
			if ( !$bQuiet ) {
				$this->output( "$startTimestamp: Running '$sScript' for instance '$instancePath' \n" );
			}
			$phpCli = \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'PhpCli' );
			$cmd = sprintf(
				"$phpCli %s %s --sfr=%s",
				MediaWiki\Shell\Shell::escape( $GLOBALS['IP'] . '/' . $sScript ),
				$sArgs,
				MediaWiki\Shell\Shell::escape( $instance->getId() )
			);
			if ( !$bQuiet ) {
				$this->output( "$cmd\n\n" );
			}

			$aResult = [];
			$iCode = 0;

			# wfShellExec rennt hier ein Problem rein (unter diversen Linux-Installationen)
			# Deshalb wechsel auf system()
			# $sResult = wfShellExec( $sCmd, $iCode );

			exec( $cmd, $aResult, $iCode ); // phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
			if ( !$bQuiet ) {
				foreach ( $aResult as $sLine ) {
					$this->output( "$sLine\n" );
				}
			}

			if ( $iCode > 0 ) {
				++$iTotalErrors;
				if ( $bQuiet ) {
					$this->error( "Running '$sScript' for instance '$instancePath'" );
				}
				$this->error( "An error occurred. Exit status = $iCode. Command: '$cmd'. Output of command:" );
				if ( $bQuiet ) {
					foreach ( $aResult as $sLine ) {
						$this->error( "$sLine\n" );
					}
				}
			}
			$endTime = new DateTime();
			$endTimetamp = $endTime->format( 'Y-m-d H:i:s' );
			$runTime = $endTime->diff( $overallStartTime );
			$runTimeStamp = $runTime->format( '%Im %Ss' );
			$this->output( "$endTimetamp: Running '$sScript' for instance '$instancePath' in $runTimeStamp\n" );
		}

		$overallEndTime = new DateTime();
		$overallEndTimetamp = $overallEndTime->format( 'Y-m-d H:i:s' );
		$overallRunTime = $overallEndTime->diff( $overallStartTime );
		$overallRunTimeStamp = $overallRunTime->format( '%Im %Ss' );

		$this->output( "$overallEndTimetamp: Finished all in $overallRunTimeStamp\n" );
		if ( $iTotalErrors > 0 ) {
			exit( 1 );
		}
	}

}

$maintClass = 'RunForAll';
require_once RUN_MAINTENANCE_IF_MAIN;
