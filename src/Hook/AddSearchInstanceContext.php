<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use MediaWiki\Config\Config;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class AddSearchInstanceContext implements BeforePageDisplayHook {

	/**
	 * @param Config $farmConfig
	 * @param IAccessStore $accessStore
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly IAccessStore $accessStore
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->farmConfig->get( 'useUnifiedSearch' ) ) {
			return;
		}
		if ( !$out->getTitle()->isSpecial( 'BSSearchCenter' ) ) {
			return;
		}
		$out->addModules( [ 'ext.bluespice.wikiFarm.search' ] );

		$availableInstances = [];
		$allTargets = $this->farmConfig->get( 'searchTargets' );
		foreach ( $allTargets as $key => $data ) {
			if ( $this->accessStore->userHasRoleOnInstance( $out->getUser(), 'reader', $data['instance'] ) ) {
				$availableInstances[$key] = $data['instance-name'];
			}
		}
		$out->addJsConfigVars( 'BSWikiFarmSearchInstances', $availableInstances );
	}
}
