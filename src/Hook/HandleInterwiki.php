<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Api\ApiBase;
use MediaWiki\Config\Config;
use MediaWiki\Interwiki\Hook\InterwikiLoadPrefixHook;

class HandleInterwiki implements InterwikiLoadPrefixHook {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @param string $prefix
	 * @param array &$iwData
	 * @return bool
	 */
	public function onInterwikiLoadPrefix( $prefix, &$iwData ) {
		$links = $this->farmConfig->get( 'interwikiLinks' ) ?: [];
		$prefix = mb_strtolower( $prefix );
		if ( $prefix !== 'w' && !str_starts_with( $prefix, 'wiki-' ) ) {
			$prefix = 'wiki-' . $prefix;
		}
		if ( isset( $links[$prefix] ) ) {
			$iwData = $links[$prefix];
			return false;
		}
		return true;
	}

	/**
	 * @param string $wikiId
	 * @param string &$prefix
	 * @return bool
	 */
	public function onGetInterwikiPrefixFromWikiId( string $wikiId, string &$prefix ) {
		$links = $this->farmConfig->get( 'interwikiLinks' ) ?: [];
		foreach ( $links as $iwPrefix => $data ) {
			if ( isset( $data['iw_wikiid'] ) && $data['iw_wikiid'] === $wikiId ) {
				$prefix = $iwPrefix;
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $interwiki
	 * @param string|null &$wikiId
	 * @return bool
	 */
	public function onGetWikiIdFromInterwikiPrefix( string $interwiki, ?string &$wikiId ) {
		$links = $this->farmConfig->get( 'interwikiLinks' ) ?: [];
		foreach ( $links as $iwPrefix => $data ) {
			if ( ( $iwPrefix === $interwiki || $iwPrefix === "wiki-$interwiki" ) && isset( $data['iw_wikiid'] ) ) {
				$wikiId = $data['iw_wikiid'];
				return false;
			}
		}
		return true;
	}

	/**
	 * @param ApiBase $api
	 * @param array &$data
	 * @return void
	 */
	public function onBSApiInterwikiStoreMakeData( $api, &$data ) {
		$links = [];
		foreach ( $this->farmConfig->get( 'interwikiLinks' ) ?: [] as $prefix => $link ) {
			$prefix = mb_strtolower( $link['iw_prefix'] ?? $prefix );
			$links[$prefix] = (object)$link;
		}

		$found = [];
		foreach ( $data as $item ) {
			$prefix = mb_strtolower( $item->iw_prefix );
			if ( isset( $links[$prefix] ) ) {
				foreach ( $links[$prefix] as $key => $value ) {
					$item->$key = $value;
				}
				$item->editable = false;
				$found[$prefix] = true;
			} else {
				$item->editable = true;
			}
		}
		foreach ( $links as $prefix => $link ) {
			if ( isset( $found[$prefix] ) ) {
				continue;
			}
			$link->editable = false;
			$data[] = $link;
		}
		// Sort by 'iw_prefix'
		usort( $data, static function ( $a, $b ) {
			return strcmp( $a->iw_prefix, $b->iw_prefix );
		} );
	}
}
