<?php

namespace BlueSpice\WikiFarm\Process\Step;

class RunUpdates extends RunMaintenanceScript {

	/**
	 * @param array $previousStepData
	 *
	 * @return array
	 */
	protected function getFormattedArgs( array $previousStepData ): array {
		return [
			'--quick',
			'--skip-config-validation',
			'--sfr', $this->getInstance()->getId(),
		];
	}

	/**
	 * @return string
	 */
	protected function getFullScriptPath() {
		return $GLOBALS['IP'] . '/maintenance/' . $this->getScriptPath();
	}

	/**
	 * @return string
	 */
	protected function getScriptPath(): string {
		return 'update.php';
	}
}
