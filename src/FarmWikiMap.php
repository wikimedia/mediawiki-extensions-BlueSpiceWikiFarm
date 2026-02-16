<?php

namespace MediaWiki\Extension\BlueSpiceWikiFarm;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;

class FarmWikiMap {

	/** @var array|null */
	private ?array $map = null;

	/**
	 * @param InstanceStore $instanceStore
	 * @param Config $config
	 */
	public function __construct(
		private readonly InstanceStore $instanceStore,
		private readonly Config $config
	) {
	}

	/**
	 * @param string $wikiId
	 * @return InstanceEntity|null
	 */
	public function getInstanceByWikiId( string $wikiId ): ?InstanceEntity {
		$this->assertLoaded();
		return $this->map[$wikiId] ?? null;
	}

	/**
	 * @return array
	 */
	public function getMap(): array {
		$this->assertLoaded();
		return $this->map;
	}

	/**
	 * @return void
	 */
	private function load() {
		$this->map = $this->instanceStore->getWikiMap();
		$this->map[$this->config->get( 'rootInstanceWikiId' )] = new RootInstanceEntity();
	}

	/**
	 * @return void
	 */
	private function assertLoaded(): void {
		if ( $this->map === null ) {
			$this->load();
		}
	}
}
