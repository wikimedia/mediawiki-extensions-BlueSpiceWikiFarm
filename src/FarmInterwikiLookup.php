<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\Config;
use MediaWiki\Interwiki\InterwikiLookup;

class FarmInterwikiLookup implements InterwikiLookup {

	/**
	 * @param InterwikiLookup $interwikiLookup
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly InterwikiLookup $interwikiLookup,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function isValidInterwiki( $prefix ) {
		return $this->interwikiLookup->isValidInterwiki( $prefix );
	}

	/**
	 * @inheritDoc
	 */
	public function fetch( $prefix ) {
		return $this->interwikiLookup->fetch( $prefix );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllPrefixes( $local = null ) {
		$prefixes = [];
		$farmLinks = $this->farmConfig->get( 'interwikiLinks' ) ?: [];
		$farmPrefixes = [];

		foreach ( $farmLinks as $link ) {
			$prefix = mb_strtolower( $link['iw_prefix'] );
			$farmPrefixes[$prefix] = true;
			$isLocal = (bool)( $link['iw_local'] ?? false );
			if ( $local === null || $local === $isLocal ) {
				$prefixes[$prefix] = $link;
			}
		}

		foreach ( $this->interwikiLookup->getAllPrefixes( $local ) as $link ) {
			$prefix = mb_strtolower( $link['iw_prefix'] );
			if ( isset( $farmPrefixes[$prefix] ) ) {
				continue;
			}
			$prefixes[$prefix] = $link;
		}

		return array_values( $prefixes );
	}

	/**
	 * @inheritDoc
	 */
	public function invalidateCache( $prefix ) {
		$this->interwikiLookup->invalidateCache( $prefix );
	}
}
