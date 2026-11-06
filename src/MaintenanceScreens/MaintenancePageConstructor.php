<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\NonExistingInstanceEntity;
use MediaWiki\Context\RequestContext;
use MediaWiki\MainConfigNames;
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
		try {
			$config = $this->services->getMainConfig();
			if ( $config->get( MainConfigNames::TmpDirectory ) === false ) {
				// Ensure temp directory
				$GLOBALS['wgTmpDirectory'] = wfTempDir();
			}
			if ( $this->instanceEntity instanceof NonExistingInstanceEntity ) {
				$context = RequestContext::getMain();
				if ( $this->services->getPermissionManager()->userHasRight( $context->getUser(), 'wikifarm-createwiki' ) ) {
					return ( new NonExistingInstanceScreen( $this->instanceEntity ) )->getHtml();
				}
				$context->getRequest()->response()->statusHeader( 404 );
				return ( new NotFoundScreen( $this->instanceEntity ) )->getHtml();
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
		} catch ( Throwable ) {
			// Something went wrong, show generic failure, better than an empty page
			return $this->showFailurePage( '', 'unknown' );
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
		return $this->showFailurePage( '', 'unknown' );
	}

	/**
	 * @return array|null
	 * @throws HttpException
	 */
	private function getProcess(): ?array {
		try {
			$db = $this->services->getDBLoadBalancer()->getConnection( DB_REPLICA );
			$process = $db->newSelectQueryBuilder()
				->select( [ 'sfp_pid pid', 'sfp_type type' ] )
				->from( 'simple_farmer_processes' )
				->where( [ 'sfp_instance' => $this->instanceEntity->getId() ] )
				->caller( __METHOD__ )
				->orderBy( 'sfp_started', 'DESC' )
				->fetchRow();
			if ( $process ) {
				$processManager = MediaWikiServices::getInstance()->getService( 'ProcessManager' );
				/** @var ProcessInfo $processInfo */
				$processInfo = $processManager->getProcessInfo( $process->pid );
				if ( !$processInfo ) {
					return null;
				}
				return [
					$process->type, $processInfo, $processInfo->getExitStateMessage() ?? 'running', $process->pid
				];
			}
			return null;
		} catch ( Throwable ) {
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
