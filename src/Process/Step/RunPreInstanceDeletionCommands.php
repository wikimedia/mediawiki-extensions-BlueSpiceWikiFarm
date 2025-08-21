<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceEntity;

class RunPreInstanceDeletionCommands extends RunInstanceExternalScripts {

	/**
	 * @return string
	 */
	protected function getActionAttributeName(): string {
		return 'BlueSpiceWikiFarmPreInstanceDeletionCommandFactories';
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $data = [] ): array {
		$res = parent::execute( $data );
		$this->instance->setStatus( InstanceEntity::STATUS_MAINTENANCE );
		$this->getInstanceManager()->getStore()->store( $this->getInstance() );
		return $res;
	}
}
