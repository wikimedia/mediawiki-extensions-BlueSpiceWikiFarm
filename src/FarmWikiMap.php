<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\Config;
use MediaWiki\Message\Message;

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
		$data = array_merge( $data, $this->getWikiInfoFromInstance( $instance ) );
	}

	/**
	 * @param InstanceEntity $instanceEntity
	 * @return array
	 */
	public function getWikiInfoFromInstance( InstanceEntity $instanceEntity ): array {
		return [
			'wiki_id' => $instanceEntity instanceof RootInstanceEntity ?
				$this->config->get( 'rootInstanceWikiId' ) :
				$instanceEntity->getWikiId(),
			'display_text' => $instanceEntity instanceof RootInstanceEntity ?
				Message::newFromKey( 'wikifarm-root-wiki-display-text' )->text() :
				$instanceEntity->getDisplayName(),
			'url' => $instanceEntity->getUrl( $this->config ),
			'color' => $instanceEntity->getMetadata()['instanceColor'] ?? null
		];
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
