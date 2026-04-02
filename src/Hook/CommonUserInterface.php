<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Component\WikiInstancesMenu;
use BlueSpice\WikiFarm\EnhancedGlobalActionsAdministration;
use BlueSpice\WikiFarm\GlobalActionsAdministration;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/** @var InstanceStore */
	private $instanceStore;

	/** @var Config */
	private $farmConfig;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/** @var IAccessStore */
	private $accessControlStore;

	/**
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param IAccessStore $accessControlStore
	 */
	public function __construct( InstanceStore $instanceStore, Config $farmConfig,
		UserOptionsLookup $userOptionsLookup, IAccessStore $accessControlStore ) {
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->accessControlStore = $accessControlStore;
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
						return new WikiInstancesMenu( $this->instanceStore,
							$this->farmConfig, $this->userOptionsLookup, $this->accessControlStore );
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
							return new EnhancedGlobalActionsAdministration();
						}
						return new GlobalActionsAdministration();
					}
				]
			]
		);
	}

}
