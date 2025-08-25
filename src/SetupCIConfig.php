<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\MultiConfig;

class SetupCIConfig extends MultiConfig {

	public function __construct() {
	}

	/** @inheritDoc */
	public function has( $name ): bool {
		return true;
	}

	/** @inheritDoc */
	public function get( $name ) {
		return false;
	}

}
