<?php

namespace BlueSpice\WikiFarm\CommandDescription;

use BlueSpice\WikiFarm\CommandDescriptionBase;

class RunJobs extends CommandDescriptionBase {

	/**
	 * @return string[]
	 */
	public function getCommandArguments() {
		$args = [
			$GLOBALS['IP'] . '/maintenance/runJobs.php'
		];

		return $args;
	}

	/**
	 *
	 * @return int
	 */
	public function getPosition() {
		return 1000;
	}

	/**
	 * This may take a while
	 * @return bool
	 */
	public function runAsync() {
		return true;
	}

}
