<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Special\UserAccess;
use BlueSpice\WikiFarm\Special\Wikis;
use MediaWiki\Config\Config;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class AddSpecialPages implements SpecialPage_initListHook {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct( private readonly Config $farmConfig ) {
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$list['UserAccess'] = [
				'class' => UserAccess::class,
				'services' => [ 'BlueSpiceWikiFarm._Config' ]
			];
		}
		if ( $this->farmConfig->get( 'shareUsers' ) ) {
			$list['Wikis'] = [
				'class' => Wikis::class
			];
		}
	}
}
