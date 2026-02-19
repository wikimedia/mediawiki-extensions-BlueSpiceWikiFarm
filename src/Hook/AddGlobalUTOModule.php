<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\SpecialPage\Hook\SpecialPageBeforeExecuteHook;

class AddGlobalUTOModule implements SpecialPageBeforeExecuteHook {

	/**
	 * @inheritDoc
	 */
	public function onSpecialPageBeforeExecute( $special, $subPage ) {
		if ( $special->getName() !== 'UnifiedTaskOverview' ) {
			return;
		}
		$special->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.unifiedTaskOverviewModule' ] );
	}
}
