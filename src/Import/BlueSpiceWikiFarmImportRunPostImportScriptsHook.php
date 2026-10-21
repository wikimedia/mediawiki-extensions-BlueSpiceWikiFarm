<?php

namespace BlueSpice\WikiFarm\Import;

use BlueSpice\WikiFarm\InstanceEntity;

interface BlueSpiceWikiFarmImportRunPostImportScriptsHook {

	/**
	 * @param array &$scripts
	 * @param InstanceEntity $instance
	 * @return void
	 */
	public function onBlueSpiceWikiFarmImportRunPostImportScripts( array &$scripts, InstanceEntity $instance ): void;
}
