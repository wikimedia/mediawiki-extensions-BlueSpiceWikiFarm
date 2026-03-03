<?php

namespace BlueSpice\WikiFarm\Hook\Integration;

use BlueSpice\WikiFarm\ExtendedSearch\GlobalSearchDataProvider;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddSearchInstanceContext implements BeforePageDisplayHook {

	/**
	 * @param GlobalSearchDataProvider $dataProvider
	 */
	public function __construct(
		private readonly GlobalSearchDataProvider $dataProvider
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->dataProvider->isAvailable() ) {
			return;
		}
		if ( !$out->getTitle()->isSpecial( 'BSSearchCenter' ) ) {
			return;
		}
		$out->addModules( [ 'ext.bluespice.wikiFarm.search' ] );

		$availableInstances = $this->dataProvider->getAvailableInstances( $out->getUser() );
		$outputData = [];
		foreach ( $availableInstances as $key => $instance ) {
			$outputData[$key] = $instance['display_data'] ? $instance['display_data']['display_text'] : $key;
		}
		$out->addJsConfigVars( 'BSWikiFarmSearchInstances', $outputData );
	}
}
