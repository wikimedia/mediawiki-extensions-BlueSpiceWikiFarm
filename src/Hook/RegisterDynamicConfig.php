<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\DynamicConfig\CentralOAuthAccessToken;
use MWStake\MediaWiki\Component\DynamicConfig\Hook\MWStakeDynamicConfigRegisterConfigsHook;

class RegisterDynamicConfig implements MWStakeDynamicConfigRegisterConfigsHook {

	/**
	 * @inheritDoc
	 */
	public function onMWStakeDynamicConfigRegisterConfigs( array &$configs ): void {
		$configs[] = new CentralOAuthAccessToken();
	}
}
