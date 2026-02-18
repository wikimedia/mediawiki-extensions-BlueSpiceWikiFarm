<?php

namespace BlueSpice\WikiFarm;

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
	 * @param string $wikiId
	 * @param array &$data
	 * @return void
	 */
	public function onGetWikiInfoFromWikiId( string $wikiId, array &$data ) {
		$instance = $this->getInstanceByWikiId( $wikiId );
		if ( !$instance ) {
			return;
		}
		$data['display_text'] = $instance->getDisplayName();
		$data['url'] = $instance->getUrl( $this->config );
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
