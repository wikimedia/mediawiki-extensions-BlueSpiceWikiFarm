<?php

namespace BlueSpice\WikiFarm\ExtendedSearch;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use BS\ExtendedSearch\Backend;
use BS\ExtendedSearch\ISearchSource;
use BS\ExtendedSearch\Lookup;
use BS\ExtendedSearch\Plugin\IFormattingModifier;
use BS\ExtendedSearch\Plugin\IIndexProvider;
use BS\ExtendedSearch\Plugin\ISearchContextProvider;
use BS\ExtendedSearch\Plugin\ISearchPlugin;
use BS\ExtendedSearch\SearchResult;
use MediaWiki\Config\Config;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;

class GlobalSearch implements
	ISearchPlugin,
	ISearchContextProvider,
	IIndexProvider,
	IFormattingModifier
{

	private array $availableInstances = [];

	private array $instancesToSearchIn = [];

	public function __construct(
		private readonly Config $farmConfig,
		private readonly IAccessStore $accessStore,
		private readonly FarmWikiMap $farmWikiMap
	) {
	}

	public function setIndices( Backend $backend, ?array $limitToSources, array &$indices ): void {
		if ( !$this->isAvailable() ) {
			return;
		}
		$allowedSources = [ 'wikipage', 'repofile' ];
		if ( is_array( $limitToSources ) ) {
			$allowedSources = array_intersect( $allowedSources, $limitToSources );
		}

		if ( empty( $this->instancesToSearchIn ) ) {
			return;
		}
		foreach ( $this->instancesToSearchIn as $data ) {
			foreach ( $allowedSources as $source ) {
				$indices[] = $data['index_prefix'] . '_' . $source;
			}
		}
	}

	public function typeFromIndexName( string $index, Backend $backend ): ?string {
		foreach ( $this->availableInstances as $data ) {
			foreach ( [ 'wikipage', 'repofile' ] as $source ) {
				if ( $index === $data['index_prefix'] . '_' . $source ) {
					return $source;
				}
			}
		}
		return null;
	}

	public function getIndexLabel( string $index ): ?string {
		return $this->getInstanceDataForIndex( $index )['display_text'];
	}

	public function getContextDefinitionForPage( PageIdentity $page, Authority $authority ): ?array {
		if ( !$this->isAvailable() ) {
			return null;
		}
		return [ 'searchInWikis' => [ '*' ] ];
	}

	public function getContextDisplayText( array $contextDefinition, UserIdentity $user, Language $language ): Message {
		return Message::newFromKey( 'wikifarm-search-context-global' );
	}

	public function showContextFilterPill(): bool {
		// Custom implementation will do it
		return false;
	}

	public function applyContext( array $contextDefinition, Authority $actor, Lookup $lookup ) {
		if ( !$this->isAvailable() ) {
			return;
		}
		$searchInWikis = $contextDefinition['searchInWikis'] ?? [ '*' ];
		$all = count( $searchInWikis ) === 1 && $searchInWikis[0] === '*';
		$this->setAvailableInstances( $actor );
		$this->instancesToSearchIn = [];
		foreach ( $this->availableInstances as $key => $data ) {
			if ( $all || in_array( $key, $searchInWikis ) ) {
				$this->instancesToSearchIn[$key] = $data;
			}
		}
	}

	public function undoContext( array $contextDefinition, Lookup $lookup ) {
		// NOOP
	}

	public function getContextKey(): string {
		return 'farm-global';
	}

	public function getContextPriority(): int {
		return 10;
	}

	public function formatFulltextResult(
		array &$result, SearchResult $resultObject, ISearchSource $source, Lookup $lookup
	): void {
		$data = $this->getInstanceDataForIndex( $resultObject->getIndex() );
		if ( $data ) {
			if ( $data['color'] && $data['color']['lightText'] ) {
				// Somehow bools gets lost in Search result processing, convert to int
				$data['color']['lightText'] = 1;
			}
			$result['source'] = $data;
		}
	}

	public function formatAutocompleteResults( array &$results, array $searchData ): void {
		// NOOP
	}

	public function modifyResultStructure( array &$resultStructure, ISearchSource $source ): void {
		// NOOP
	}

	/**
	 * @param string $index
	 * @return array|null
	 */
	private function getInstanceDataForIndex( string $index ): ?array {
		foreach ( $this->availableInstances as $data ) {
			foreach ( [ 'wikipage', 'repofile' ] as $source ) {
				if ( $index === $data['index_prefix'] . '_' . $source ) {
					return $this->farmWikiMap->getWikiInfoFromInstance( $data['instance'] );
				}
			}
		}
		return null;
	}

	/**
	 * @param Authority $actor
	 * @return void
	 */
	private function setAvailableInstances( Authority $actor ) {
		$this->availableInstances = [];
		$allTargets = $this->farmConfig->get( 'searchTargets' );
		foreach ( $allTargets as $key => $data ) {
			if ( $this->accessStore->userHasRoleOnInstance( $actor->getUser(), 'reader', $data['instance'] ) ) {
				$this->availableInstances[$key] = $data;
			}
		}
	}

	/**
	 * @return bool
	 */
	private function isAvailable(): bool {
		return $this->farmConfig->get( 'useUnifiedSearch' ) && $this->farmConfig->get( 'shareUsers' );
	}

}
