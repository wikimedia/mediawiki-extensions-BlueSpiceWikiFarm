<?php

namespace BlueSpice\WikiFarm\ExtendedSearch;

use BS\ExtendedSearch\ISearchSource;
use BS\ExtendedSearch\Lookup;
use BS\ExtendedSearch\Plugin\IFormattingModifier;
use BS\ExtendedSearch\Plugin\ISearchContextProvider;
use BS\ExtendedSearch\Plugin\ISearchPlugin;
use BS\ExtendedSearch\SearchResult;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

class GlobalSearch implements
	ISearchPlugin,
	ISearchContextProvider,
	IFormattingModifier
{

	/** @var array */
	private array $instancesToSearchIn = [];

	private array $indexInstanceDisplayMapping = [];

	/**
	 * @param GlobalSearchDataProvider $dataProvider
	 */
	public function __construct(
		private readonly GlobalSearchDataProvider $dataProvider
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function getContextDefinitionForPage( PageIdentity $page, Authority $authority ): ?array {
		if ( !$this->dataProvider->isAvailable() ) {
			return null;
		}
		return [ 'searchInWikis' => [ '*' ] ];
	}

	/**
	 * @inheritDoc
	 */
	public function getContextDisplayText( array $contextDefinition, UserIdentity $user, Language $language ): Message {
		return Message::newFromKey( 'wikifarm-search-context-global' );
	}

	/**
	 * @inheritDoc
	 */
	public function showContextFilterPill(): bool {
		// Custom implementation will do it
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function applyContext( array $contextDefinition, Authority $actor, Lookup $lookup ) {
		if ( !$this->dataProvider->isAvailable() ) {
			return;
		}
		$searchInWikis = $contextDefinition['searchInWikis'] ?? [ '*' ];
		$all = count( $searchInWikis ) === 1 && $searchInWikis[0] === '*';
		$availableInstances = $this->dataProvider->getAvailableInstances( $actor );
		$this->instancesToSearchIn = [];
		foreach ( $availableInstances as $key => $data ) {
			if ( $all || in_array( $key, $searchInWikis ) ) {
				$this->instancesToSearchIn[] = $data['wiki_id'];
				$this->indexInstanceDisplayMapping[$data['wiki_id']] = $data['display_data'];
			}
		}
		if (
			!in_array( WikiMap::getCurrentWikiId(), $this->instancesToSearchIn ) &&
			array_key_exists( FARMER_CALLED_INSTANCE, $availableInstances )
		) {
			// Assure current wiki is included, if readable
			$this->instancesToSearchIn[] = WikiMap::getCurrentWikiId();
		}
		if ( !empty( $this->instancesToSearchIn ) ) {
			$lookup->addTermsFilter( 'wiki_id', $this->instancesToSearchIn );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function undoContext( array $contextDefinition, Lookup $lookup ) {
		if ( !empty( $this->instancesToSearchIn ) ) {
			$lookup->removeTermsFilter( 'wiki_id', $this->instancesToSearchIn );
		}
	}

	/**
	 * @inheritDoc
	 */
	public function getContextKey(): string {
		return 'farm-global';
	}

	/**
	 * @inheritDoc
	 */
	public function getContextPriority(): int {
		return 10;
	}

	/**
	 * @inheritDoc
	 */
	public function formatFulltextResult(
		array &$result, SearchResult $resultObject, ISearchSource $source, Lookup $lookup
	): void {
		$data = $this->indexInstanceDisplayMapping[$result['wiki_id']] ?? null;
		if ( $data ) {
			if ( $data['color'] && $data['color']['lightText'] ) {
				// Somehow bools gets lost in Search result processing, convert to int
				$data['color']['lightText'] = 1;
			}
			$result['source'] = $data;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function formatAutocompleteResults( array &$results, array $searchData ): void {
		// NOOP
	}

	/**
	 * @inheritDoc
	 */
	public function modifyResultStructure( array &$resultStructure, ISearchSource $source ): void {
		// NOOP
	}
}
