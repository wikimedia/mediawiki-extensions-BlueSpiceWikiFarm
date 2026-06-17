<?php

namespace BlueSpice\WikiFarm\ExtendedSearch;

use BlueSpice\WikiFarm\ExtendedSearch\LookupModifier\WikiIdAggregation;
use BS\ExtendedSearch\ILookupModifierProvider;
use BS\ExtendedSearch\ISearchSource;
use BS\ExtendedSearch\Lookup;
use BS\ExtendedSearch\Plugin\IFilterModifier;
use BS\ExtendedSearch\Plugin\IFormattingModifier;
use BS\ExtendedSearch\Plugin\ISearchContextProvider;
use BS\ExtendedSearch\Plugin\ISearchPlugin;
use BS\ExtendedSearch\SearchResult;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

class GlobalSearch implements
	ISearchPlugin,
	ISearchContextProvider,
	IFormattingModifier,
	ILookupModifierProvider,
	IFilterModifier
{

	/** @var array */
	private array $instancesToSearchIn = [];

	private array $indexInstanceDisplayMapping = [];

	/** @var string */
	private string $currentInstance;

	/**
	 * @param GlobalSearchDataProvider $dataProvider
	 */
	public function __construct(
		private readonly GlobalSearchDataProvider $dataProvider
	) {
		$this->currentInstance = WikiMap::getCurrentWikiId();
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
		$availableInstances = $this->setInstancesToSearchIn( $actor, $all, $searchInWikis );
		if (
			!in_array( $this->currentInstance, $this->instancesToSearchIn ) &&
			array_key_exists( FARMER_CALLED_INSTANCE, $availableInstances )
		) {
			// Assure current wiki is included, if readable
			$this->instancesToSearchIn[] = $this->currentInstance;
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
		$result = $this->decorateResultWithSource( $result );
	}

	/**
	 * @inheritDoc
	 */
	public function formatAutocompleteResults( array &$results, array $searchData ): void {
		$this->setInstancesToSearchIn( RequestContext::getMain()->getUser(), true );
		foreach ( $results as &$result ) {
			$result = $this->decorateResultWithSource( $result );
		}
	}

	/**
	 * @param array $result
	 * @return array
	 */
	private function decorateResultWithSource( array $result ): array {
		$data = $this->indexInstanceDisplayMapping[$result['wiki_id']] ?? null;
		if ( $data ) {
			if ( $data['color'] && $data['color']['lightText'] ) {
				// Somehow bools gets lost in Search result processing, convert to int
				$data['color']['lightText'] = 1;
			}
			$result['source'] = $data;
			$result['source']['is_local'] = $result['wiki_id'] === $this->currentInstance ? 1 : 0;
		}
		return $result;
	}

	/**
	 * @param Authority $actor
	 * @param bool $all
	 * @param array $searchInWikis
	 * @return array Instances to search in
	 */
	private function setInstancesToSearchIn( Authority $actor, bool $all, array $searchInWikis = [] ): array {
		$availableInstances = $this->dataProvider->getAvailableInstances( $actor );
		$this->instancesToSearchIn = [];
		foreach ( $availableInstances as $key => $data ) {
			if ( $all || in_array( $key, $searchInWikis ) ) {
				$this->instancesToSearchIn[] = $data['wiki_id'];
				$this->indexInstanceDisplayMapping[$data['wiki_id']] = array_merge(
					[ 'path' => $key ],
					$data['display_data']
				);
			}
		}
		return $availableInstances;
	}

	/**
	 * @inheritDoc
	 */
	public function modifyResultStructure( array &$resultStructure, ISearchSource $source ): void {
		// NOOP
	}

	/**
	 * @inheritDoc
	 */
	public function getLookupModifiers( Lookup $lookup, IContextSource $context ): array {
		return [
			new WikiIdAggregation( $lookup, $context ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function modifyFilters( array &$aggregations, array &$filterCfg, array $fieldsWithANDEnabled, ISearchSource $source ): void {
		foreach ( $filterCfg['wiki_id']['buckets'] ?? [] as $i => $bucket ) {
			$wikiId = $bucket['key'];
			if ( isset( $this->indexInstanceDisplayMapping[$wikiId] ) ) {
				$filterCfg['wiki_id']['buckets'][$i]['key'] = $this->indexInstanceDisplayMapping[$wikiId]['path'];
				$filterCfg['wiki_id']['buckets'][$i]['label'] = $this->indexInstanceDisplayMapping[$wikiId]['display_text'];
			}
		}
	}
}
