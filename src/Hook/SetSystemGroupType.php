<?php

namespace BlueSpice\WikiFarm\Hook;

use MWStake\MediaWiki\Component\DynamicConfig\Hook\MWStakeDynamicConfigManagerLoadedHook;

class SetSystemGroupType implements MWStakeDynamicConfigManagerLoadedHook {

	/**
	 * Set all `wiki_*_{role} groups as coming from extension
	 * @return void
	 */
	public function onMWStakeDynamicConfigManagerLoaded(): void {
		$groups = $GLOBALS['wgAdditionalGroups'] ?? [];
		foreach ( $groups as $group => $type ) {
			if ( preg_match( '/^wiki_.*_(reader|editor|reviewer|admin)$/', $group ) ) {
				$GLOBALS['wgGroupTypes'][$group] = 'extension-minimal';
			}
		}
	}
}
