<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddBootstrap implements BeforePageDisplayHook {

	/**
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly InstanceStore $instanceStore,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$out->addModules( [ 'ext.bluespice.wikiFarm.bootstrap' ] );
		$instance = $this->instanceStore->getCurrentInstance();
		if ( $instance && $instance->getMetadata()['instanceColor'] ) {
			$out->getOutput()->addJsConfigVars(
				'bsWikiFarmInstanceColor', $instance->getMetadata()['instanceColor']
			);
		}
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$out->addModuleStyles( [ 'ext.bluespice.wikiFarm.megamenu', 'ext.bluespice.wikiFarm.breadcrumbs' ] );
		}
	}
}
