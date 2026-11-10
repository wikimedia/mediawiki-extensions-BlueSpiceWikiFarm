<?php

namespace BlueSpice\WikiFarm\Hook\Integration;

use BlueSpice\WikiFarm\FarmInterwikiLookup;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\MediaWikiServices;

class ConfigureInterwikiLookup implements MediaWikiServicesHook {

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ) {
		$services->addServiceManipulator(
			'InterwikiLookup',
			static function ( InterwikiLookup $interwikiLookup, MediaWikiServices $services ): InterwikiLookup {
				return new FarmInterwikiLookup(
					$interwikiLookup,
					$services->getService( 'BlueSpiceWikiFarm._Config' )
				);
			}
		);
	}
}
