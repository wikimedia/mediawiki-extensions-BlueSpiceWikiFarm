<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;

abstract class InstanceAwareStep implements IProcessStep {
	/** @var InstanceEntity|null */
	protected $instance = null;

	/** @var InstanceManager */
	protected $instanceManager;

	/**
	 * @param InstanceManager $instanceManager
	 * @param string $instanceId
	 * @throws Exception
	 */
	public function __construct( InstanceManager $instanceManager, string $instanceId ) {
		$this->instanceManager = $instanceManager;
		$this->obtainInstance( $instanceId );
	}

	public function getInstance(): InstanceEntity {
		return $this->instance;
	}

	/**
	 * @return InstanceManager
	 */
	public function getInstanceManager(): InstanceManager {
		return $this->instanceManager;
	}

	/**
	 * @param string $instanceId
	 * @return void
	 * @throws Exception
	 */
	private function obtainInstance( string $instanceId ) {
		if ( !$instanceId ) {
			throw new Exception( 'No instanceId given' );
		}
		$this->instanceManager->getStore()->reloadInstance( $instanceId );
		$this->instance = $this->instanceManager->getStore()->getInstanceById( $instanceId );
		if ( !( $this->instance instanceof InstanceEntity ) ) {
			throw new Exception( "Instance with ID $instanceId not found" );
		}
	}
}
