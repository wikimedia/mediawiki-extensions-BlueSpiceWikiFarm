<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use BlueSpice\WikiFarm\Special\AccessManagement;
use BlueSpice\WikiFarm\Special\Wikis;
use MediaWiki\Config\Config;
use MediaWiki\SpecialPage\Hook\SpecialPage_initListHook;

class AddSpecialPages implements SpecialPage_initListHook {

	/**
	 * @param Config $farmConfig
	 * @param InstanceCountLimiter $countLimiter
	 */
	public function __construct( private readonly Config $farmConfig,
		private readonly InstanceCountLimiter $countLimiter ) {
	}

	/** @inheritDoc */
	public function onSpecialPage_initList( &$list ) {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$list['AccessManagement'] = [
				'class' => AccessManagement::class,
				'services' => [ 'BlueSpiceWikiFarm._Config' ]
			];
		}
		if ( $this->farmConfig->get( 'shareUsers' ) ) {
			$list['Wikis'] = [
				'class' => Wikis::class,
				'services' => [ 'BlueSpiceWikiFarm._InstanceCountLimiter' ]
			];
		}
	}
}
