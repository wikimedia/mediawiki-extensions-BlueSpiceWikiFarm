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
		$out->addModuleStyles( [ 'ext.bluespice.wikiFarm.instanceColors' ] );
		$instance = $this->instanceStore->getCurrentInstance();
		$instanceColor = $instance ? ( $instance->getMetadata()['instanceColor'] ?? null ) : null;
		if ( $instanceColor ) {
			$out->addJsConfigVars( 'bsWikiFarmInstanceColor', $instanceColor );
			$color = $instanceColor['background'] ?? null;
			if ( $color !== null ) {
				$out->addInlineStyle( ":root { --bs-wiki-color: $color; }" );
			}
		}
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$out->addModuleStyles( [ 'ext.bluespice.wikiFarm.breadcrumbs' ] );
		}
	}
}
