<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\FarmWikiMap;
use MediaWiki\Config\Config;
use MediaWiki\Extension\WikiMCPTools\Hook\WikiMCPToolsGetWikiMapHook;

class PopulateWikiMap implements WikiMCPToolsGetWikiMapHook {

	/**
	 * @param FarmWikiMap $wikiMap
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly FarmWikiMap $wikiMap,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @param array &$map
	 * @return void
	 */
	public function onWikiMCPToolsGetWikiMap( &$map ) {
		$wikis = $this->wikiMap->getMap();
		foreach ( $wikis as $wikiId => $instance ) {
			$map[$wikiId] = $instance->getScriptPath( $this->farmConfig );
		}
	}
}
