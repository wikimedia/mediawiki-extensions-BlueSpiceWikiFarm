<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddBootstrap implements BeforePageDisplayHook {

	/**
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		private readonly InstanceStore $instanceStore
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.bluespice.wikiFarm.bootstrap' ] );
		$out->addModuleStyles( [ 'ext.bluespice.wikiFarm.megamenu' ] );
		$instance = $this->instanceStore->getCurrentInstance();
		if ( $instance && $instance->getMetadata()['instanceColor'] ) {
			$out->getOutput()->addJsConfigVars(
				'bsWikiFarmInstanceColor', $instance->getMetadata()['instanceColor']
			);
		}
	}
}
