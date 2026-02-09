<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\WikiMap\WikiMap;
use MWStake\MediaWiki\Component\MCP\Hook\MWStakeMCPGetWikiMapHook;

class PopulateWikiMap implements MWStakeMCPGetWikiMapHook {

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
	public function onMWStakeMCPGetWikiMap( &$map ): void {
		$instances = $this->instanceStore->getAllInstances();
		foreach ( $instances as $instance ) {
			$dbDomain = $instance->getDatabaseDomain();
			$instanceWikiId = WikiMap::getWikiIdFromDbDomain( $dbDomain );
			$map[$instanceWikiId] = $instance->getScriptPath( $this->farmConfig );
		}
	}
}
