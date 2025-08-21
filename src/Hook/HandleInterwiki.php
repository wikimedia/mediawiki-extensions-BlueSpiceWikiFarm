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
		$links = $this->farmConfig->get( 'interwikiLinks' );
		$prefix = mb_strtolower( $prefix );
		if ( isset( $links[$prefix] ) ) {
			$iwData = $links[$prefix];
			return false;
		}
		return true;
	}

	/**
	 * @param ApiBase $api
	 * @param array &$data
	 * @return void
	 */
	public function onBSApiInterwikiStoreMakeData( $api, &$data ) {
		foreach ( $data as $item ) {
			$item->editable = true;
		}
		$links = $this->farmConfig->get( 'interwikiLinks' );
		foreach ( $links as $prefix => $link ) {
			$link = (object)$link;
			$link->editable = false;
			$data[] = $link;
		}
		// Sort by 'iw_prefix'
		usort( $data, static function ( $a, $b ) {
			return strcmp( $a->iw_prefix, $b->iw_prefix );
		} );
	}
}
