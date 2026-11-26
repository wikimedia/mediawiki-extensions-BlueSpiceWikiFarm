<?php

namespace BlueSpice\WikiFarm\Hook\Integration;

use BlueSpice\WikiFarm\FarmWikiMap;
use MediaWiki\Extension\NotifyMe\Hook\NotifyMeSearchInWikisHook;

class NotifyMeSetSearchableWikis implements NotifyMeSearchInWikisHook {

	/**
	 * @param FarmWikiMap $farmWikiMap
	 */
	public function __construct(
		private readonly FarmWikiMap $farmWikiMap
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onNotifyMeSearchInWikis( array &$wikis ): void {
		$wikis = $this->farmWikiMap->getAllWikiIds();
	}
}
