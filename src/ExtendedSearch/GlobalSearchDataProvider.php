<?php

namespace BlueSpice\WikiFarm\ExtendedSearch;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\Authority;

class GlobalSearchDataProvider {

	/** @var array|null */
	private ?array $availableInstances = null;

	/**
	 * @param Config $farmConfig
	 * @param IAccessStore $accessStore
	 * @param FarmWikiMap $farmWikiMap
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly IAccessStore $accessStore,
		private readonly FarmWikiMap $farmWikiMap,
		private readonly InstanceStore $instanceStore,
	) {
	}

	/**
	 * @param Authority $actor
	 * @return array
	 */
	public function getAvailableInstances( Authority $actor ): array {
		if ( $this->availableInstances === null ) {
			$this->availableInstances = [];
			$allTargets = $this->getTargets();
			foreach ( $allTargets as $key => $data ) {
				if ( $this->accessStore->userHasRoleOnInstance( $actor->getUser(), 'reader', $data['instance'] ) ) {
					$this->availableInstances[$key] = $data;
				}
			}
		}
		return $this->availableInstances;
	}

	/**
	 * @return bool
	 */
	public function isAvailable(): bool {
		return $this->farmConfig->get( 'useUnifiedSearch' ) && $this->farmConfig->get( 'shareUsers' );
	}

	/**
	 * @return array
	 */
	private function getTargets(): array {
		$targets = [];

		if ( !FARMER_IS_ROOT_WIKI_CALL && $this->farmConfig->get( 'searchInMainInstance' ) ) {
			$instance = new RootInstanceEntity(
				$this->farmConfig->get( 'managementDBname' ), $this->farmConfig->get( 'managementDBprefix' )
			);
			$targets['w'] = [
				'wiki_id' => $instance->getWikiId(),
				'instance' => $instance,
				'display_data' => $this->farmWikiMap->getWikiInfoFromInstance( $instance ),
			];
		}

		foreach ( $this->instanceStore->getAllInstances() as $instance ) {
			if ( $instance->getPath() === FARMER_CALLED_INSTANCE ) {
				// Do not search in the current instance
				continue;
			}
			if ( !$instance->isActive() ) {
				continue;
			}

			$metadata = $instance->getMetadata();
			if ( isset( $metadata['notsearchable'] ) && $metadata['notsearchable'] ) {
				continue;
			}

			$targets[$instance->getPath()] = [
				'wiki_id' => $instance->getWikiId(),
				'instance' => $instance,
				'display_data' => $this->farmWikiMap->getWikiInfoFromInstance( $instance ),
			];
		}

		return $targets;
	}
}
