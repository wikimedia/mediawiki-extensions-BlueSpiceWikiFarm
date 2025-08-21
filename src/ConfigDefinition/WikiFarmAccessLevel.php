<?php

namespace BlueSpice\WikiFarm\ConfigDefinition;

use BlueSpice\ConfigDefinition\IOverwriteGlobal;
use BlueSpice\ConfigDefinition\StringSetting;

class WikiFarmAccessLevel extends StringSetting implements IOverwriteGlobal {

	/**
	 * @return string
	 */
	public function getGlobalName() {
		return 'wgWikiFarmAccessLevel';
	}

	/**
	 * @return string
	 */
	public function getLabelMessageKey() {
		return '';
	}

	/**
	 * @return true
	 */
	public function isHidden() {
		return true;
	}
}
