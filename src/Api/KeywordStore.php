<?php

namespace BlueSpice\WikiFarm\Api;

class KeywordStore extends GroupStore {

	/**
	 * @inheritDoc
	 */
	protected function appendResults( string $q, array $metadata ) {
		if ( isset( $metadata['keywords'] ) && $metadata['keywords'] ) {
			foreach ( $metadata['keywords'] as $keyword ) {
				if ( !$q || $this->matchesQuery( $q, $keyword ) ) {
					$this->results[$keyword] = (object)[
						'text' => $keyword
					];
				}
			}
		}
	}

	/**
	 * @param string $q
	 * @param string $keyword
	 * @return bool
	 */
	private function matchesQuery( string $q, string $keyword ) {
		$normalQuery = strtolower( $q );
		$normalKeyWord = strtolower( $keyword );
		return strpos( $normalKeyWord, $normalQuery ) !== false;
	}
}
