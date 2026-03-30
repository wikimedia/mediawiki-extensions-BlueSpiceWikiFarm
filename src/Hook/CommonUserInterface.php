<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Component\WikiInstancesMenu;
use BlueSpice\WikiFarm\EnhancedGlobalActionsFarmManagement;
use BlueSpice\WikiFarm\GlobalActionsAccessManagement;
use BlueSpice\WikiFarm\GlobalActionsFarmManagement;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/** @var Config */
	private $farmConfig;

	/**
	 * @param Config $farmConfig
	 */
	public function __construct( Config $farmConfig ) {
		$this->farmConfig = $farmConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$skin = RequestContext::getMain()->getSkin();
		$registry->register(
			'NavbarPrimaryCenterItems',
			[
				"farm-wikis-item" => [
					'factory' => function () {
						return new WikiInstancesMenu(
							$this->farmConfig );
					}
				]
			]
		);

		$registry->register(
			'GlobalActionsAdministration',
			[
				'ga-bluespice-farmmanagement' => [
					'factory' => static function () use ( $skin ) {
						if ( is_a( $skin, 'SkinBlueSpiceEclipseSkin', true ) ) {
							return new EnhancedGlobalActionsFarmManagement();
						}
						return new GlobalActionsFarmManagement();
					}
				]
			]
		);

		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$registry->register(
				'GlobalActionsAdministration',
				[
					'ga-bluespice-accessmanagement' => [
						'factory' => static function () {
							return new GlobalActionsAccessManagement();
						}
					]
				]
			);
		}
	}

}
