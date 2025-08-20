<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Component\WikiInstancesMenu;
use BlueSpice\WikiFarm\GlobalActionsAdministration;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/** @var InstanceStore */
	private $instanceStore;

	/** @var Config */
	private $farmConfig;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( InstanceStore $instanceStore, Config $farmConfig, UserOptionsLookup $userOptionsLookup ) {
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$instanceStore = $this->instanceStore;
		$farmConfig = $this->farmConfig;
		$userOptionsLookup = $this->userOptionsLookup;
		$registry->register(
			'NavbarPrimaryCenterItems',
			[
				"farm-wikis-item" => [
					'factory' => static function () use ( $instanceStore, $farmConfig, $userOptionsLookup ) {
						return new WikiInstancesMenu( $instanceStore, $farmConfig, $userOptionsLookup );
					}
				]
			]
		);

		$registry->register(
			'GlobalActionsAdministration',
			[
				'ga-bluespice-farmmanagement' => [
					'factory' => static function () {
						return new GlobalActionsAdministration();
					}
				]
			]
		);
	}

}
