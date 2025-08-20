<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddBootstrap implements BeforePageDisplayHook {

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.bluespice.wikiFarm.bootstrap' ] );

		$out->addModuleStyles( [ 'ext.bluespice.wikiFarm.megamenu' ] );
	}
}
