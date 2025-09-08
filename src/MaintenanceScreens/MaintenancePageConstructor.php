<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\NonExistingInstanceEntity;
use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\HttpException;
use MWStake\MediaWiki\Component\ProcessManager\ProcessInfo;
use Throwable;

class MaintenancePageConstructor {

	/**
	 * @var InstanceEntity
	 */
	private $instanceEntity;

	/**
	 * @var MediaWikiServices
	 */
	private $services;

	/**
	 * @param MediaWikiServices $services
	 * @param InstanceEntity $instanceEntity
	 */
	public function __construct( MediaWikiServices $services, InstanceEntity $instanceEntity ) {
		$this->services = $services;
		$this->instanceEntity = $instanceEntity;
		$GLOBALS['wgMessagesDirs']['BlueSpiceWikiFarm'] = __DIR__ . '/../../i18n';
	}

	/**
	 * @return string
	 * @throws HttpException
	 */
	public function getHtml(): string {
		if ( $this->instanceEntity instanceof NonExistingInstanceEntity ) {
			return ( new NonExistingInstanceScreen( $this->instanceEntity ) )->getHtml();
		}
		switch ( $this->instanceEntity->getStatus() ) {
			case InstanceEntity::STATUS_INIT:
			case InstanceEntity::STATUS_INSTALLED:
				return $this->showCreationPage();
			case InstanceEntity::STATUS_SUSPENDED:
				return ( new SuspendedScreen( $this->instanceEntity ) )->getHtml();
			case InstanceEntity::STATUS_MAINTENANCE:
			default:
				return ( new MaintenanceScreen( $this->instanceEntity ) )->getHtml();
		}
	}

	/**
	 * @return string|null
	 * @throws HttpException
	 */
	private function showCreationPage(): ?string {
		[ $type, $process, $status, $pid ] = $this->getProcess();
		if ( $status === 'running' && $process ) {
			return $this->showProcessProgressPage( $type, $process );
		}
		if ( $status === 'failed' ) {
			return $this->showFailurePage( $type, $pid );
		}
		return '';
	}

	/**
	 * @return array|null
	 * @throws HttpException
	 */
	private function getProcess(): ?array {
		try {
			$db = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );
			$running = $db->newSelectQueryBuilder()
				->select( [ 'sfp_pid pid', 'sfp_pid type' ] )
				->from( 'simple_farmer_processes' )
				->join( 'processes', 'p', [ 'p.p_pid = simple_farmer_processes.sfp_pid' ] )
				->where( [ 'sfp_instance' => $this->instanceEntity->getId(), 'p.p_state' => [ 'ready', 'started' ] ] )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $running ) {
				$processManager = MediaWikiServices::getInstance()->getService( 'ProcessManager' );
				$processInfo = $processManager->getProcessInfo( $running->pid );
				if ( !$processInfo ) {
					return null;
				}
				return [ $running->type, $processInfo, 'running', $running->pid ];
			}
			$failed = $db->newSelectQueryBuilder()
				->select( [ 'sfp_pid pid', 'sfp_type type' ] )
				->from( 'simple_farmer_processes' )
				->join( 'processes', 'p', [ 'p.p_pid = simple_farmer_processes.sfp_pid' ] )
				->where( [ 'sfp_instance' => $this->instanceEntity->getId(), 'p.p_state' => 'terminated', 'p.p_exitstatus' => 'failed' ] )
				->caller( __METHOD__ )
				->fetchRow();
			if ( $failed ) {
				return [ $failed->type, null, 'failed', $failed->pid ];
			}
			return null;
		} catch ( Throwable $ex ) {
			return null;
		}
	}

	/**
	 * @param string $type
	 * @param ProcessInfo $process
	 * @return string
	 */
	private function showProcessProgressPage( string $type, ProcessInfo $process ) {
		// For now, creation (incl. cloning) is the only process type
		$screen = new CreationProcessProgress(
			$this->instanceEntity, $process->getStepProgress()
		);

		return $screen->getHtml();
	}

	/**
	 * @param string $type
	 * @param string $pid
	 * @return string
	 */
	private function showFailurePage( string $type, string $pid ) {
		$screen = new CreationFailure( $this->instanceEntity, $pid );

		return $screen->getHtml();
	}
}
