<?php

namespace BlueSpice\WikiFarm\Process\Step;

class RunPostInstanceDeletionCommands extends RunInstanceExternalScripts {

	/**
	 * @return string
	 */
	protected function getActionAttributeName(): string {
		return 'BlueSpiceWikiFarmPostInstanceDeletionCommandFactories';
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $data = [] ): array {
		$res = parent::execute( $data );
		$this->getInstanceManager()->removeInstance( $this->getInstance() );
		return $res;
	}
}
