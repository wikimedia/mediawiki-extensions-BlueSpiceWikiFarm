<?php

namespace BlueSpice\WikiFarm\Hook\BeforeInitialize;

use BlueSpice\WikiFarm\Setup;
use DeferredUpdates;
use MediaWiki\Hook\BeforeInitializeHook;
use MWStake\MediaWiki\Component\DataStash\StashManager;

class TrackLastVisitedInstances implements BeforeInitializeHook {

	private const MAX_VISITED_INSTANCES = 10;

	/**
	 * @param StashManager $stashManager
	 */
	public function __construct(
		private readonly StashManager $stashManager
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize(
		$title,
		$unused,
		$output,
		$user,
		$request,
		$mediaWikiEntryPoint
	) {
		if ( defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && FARMER_IS_ROOT_WIKI_CALL ) {
			return;
		}
		DeferredUpdates::addCallableUpdate( function () use ( $user ) {
			$visited = $this->stashManager->getGlobal( Setup::LAST_VISITED_STASH_KEY, $user ) ?? [];
			// Unset current instance from old data, so we can prepend it
			$visited = array_filter( $visited, static function ( $instance ) {
				return $instance !== FARMER_CALLED_INSTANCE;
			} );
			array_unshift( $visited, FARMER_CALLED_INSTANCE );

			$visited = array_slice( $visited, 0, static::MAX_VISITED_INSTANCES );
			$this->stashManager->stashGlobally( Setup::LAST_VISITED_STASH_KEY, $visited, $user );
		} );
	}
}
