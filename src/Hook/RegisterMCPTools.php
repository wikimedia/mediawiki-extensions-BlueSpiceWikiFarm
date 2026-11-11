<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\MCP\ListWikis;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\MediaWikiServices;

class RegisterMCPTools implements MediaWikiServicesHook {

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	public function onMediaWikiServices( $services ): void {
		if ( !$services->hasService( 'MCP.ToolRegistry' ) ) {
			return;
		}
		$services->getService( 'MCP.ToolRegistry' )->registerTool( new ListWikis() );
	}
}
