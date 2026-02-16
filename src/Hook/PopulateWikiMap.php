<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Config\Config;
use MediaWiki\Extension\BlueSpiceWikiFarm\FarmWikiMap;
use MWStake\MediaWiki\Component\MCP\Hook\MWStakeMCPGetWikiMapHook;

class PopulateWikiMap implements MWStakeMCPGetWikiMapHook {

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
	 * @inheritDoc
	 */
	public function onMWStakeMCPGetWikiMap( &$map ): void {
		$wikis = $this->wikiMap->getMap();
		foreach ( $wikis as $wikiId => $instance ) {
			$map[$wikiId] = $instance->getScriptPath( $this->farmConfig );
		}
	}
}
