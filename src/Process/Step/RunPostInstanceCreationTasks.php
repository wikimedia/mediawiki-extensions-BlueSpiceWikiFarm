<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceEntity;

class RunPostInstanceCreationTasks extends RunInstanceExternalScripts {

	/**
	 * @return string
	 */
	protected function getActionAttributeName(): string {
		return 'BlueSpiceWikiFarmPostInstanceCreationCommandFactories';
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $data = [] ): array {
		$res = parent::execute( $data );
		$this->instance->setStatus( InstanceEntity::STATUS_READY );
		$this->getInstanceManager()->getStore()->store( $this->getInstance() );
		$this->getInstanceManager()->getStore()->clearRunningProcesses( $this->instance );
		return $res;
	}
}
