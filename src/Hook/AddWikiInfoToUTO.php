<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\FarmWikiMap;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Extension\UnifiedTaskOverview\Hook\TaskCollectionCompleteHook;
use MediaWiki\WikiMap\WikiMap;

class AddWikiInfoToUTO implements TaskCollectionCompleteHook {

	/**
	 * @param FarmWikiMap $wikiMap
	 * @param Config $farmConfig
	 */
	public function __construct( private readonly FarmWikiMap $wikiMap,
		private readonly Config $farmConfig ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onUnifiedTaskOverviewTaskCollectionComplete( &$tasks ) {
		$currentWikiId = WikiMap::getCurrentWikiId();
		foreach ( $tasks as &$task ) {
			if ( !$task['wiki_id'] ) {
				continue;
			}
			$wikiId = $task['wiki_id'];
			$instance = $this->wikiMap->getInstanceByWikiId( $wikiId );
			if ( !$instance ) {
				continue;
			}
			$task['source'] = [
				'color' => $instance->getMetadata()['instanceColor'] ?? null,
				'display_text' => $instance->getDisplayName(),
				'path' => $instance->getPath(),
				'url' => $instance->getUrl( $this->farmConfig ),
				'is_root' => $instance instanceof RootInstanceEntity
			];

			if ( $wikiId !== $currentWikiId && !empty( $task['page_title'] ) ) {
				$task['url'] = $this->makePageUrl( $instance, $task['page_title'] );
			}
		}
	}

	/**
	 * Tasks are collected across all wikis of the farm, but their URL is built by the
	 * wiki that reads them - which is the wrong wiki for everything that comes from
	 * another instance. Those tasks have to link into the instance they live on.
	 *
	 * @param InstanceEntity $instance
	 * @param string $pageTitle Prefixed DB key of the page the task belongs to
	 * @return string
	 */
	private function makePageUrl( InstanceEntity $instance, string $pageTitle ): string {
		if ( $instance instanceof RootInstanceEntity ) {
			$base = rtrim( $this->farmConfig->get( 'globalServer' ), '/' ) .
				rtrim( $this->farmConfig->get( 'basePath' ), '/' );
		} else {
			$base = $instance->getUrl( $this->farmConfig );
		}

		return rtrim( $base, '/' ) . '/wiki/' . wfUrlencode( str_replace( ' ', '_', $pageTitle ) );
	}
}
