<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Component\CreateInstanceButton;
use BlueSpice\WikiFarm\Component\WikiInstancesMenu;
use BlueSpice\WikiFarm\EnhancedGlobalActionsFarmManagement;
use BlueSpice\WikiFarm\GlobalActionsAccessManagement;
use BlueSpice\WikiFarm\GlobalActionsFarmManagement;
use BlueSpice\WikiFarm\InstanceCountLimiter;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\Hook\MWStakeCommonUIRegisterSkinSlotComponents;

class CommonUserInterface implements MWStakeCommonUIRegisterSkinSlotComponents {

	/** @var Config */
	private $farmConfig;

	/**
	 * @param Config $farmConfig
	 * @param TitleFactory $titleFactory
	 * @param PermissionManager $permissionManager
	 * @param SpecialPageFactory $spf
	 * @param InstanceCountLimiter $countLimiter
	 */
	public function __construct(
		Config $farmConfig,
		private readonly TitleFactory $titleFactory,
		private readonly PermissionManager $permissionManager,
		private readonly SpecialPageFactory $spf,
		private readonly InstanceCountLimiter $countLimiter
	) {
		$this->farmConfig = $farmConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function onMWStakeCommonUIRegisterSkinSlotComponents( $registry ): void {
		$context = RequestContext::getMain();
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
					'factory' => function () use ( $skin ) {
						if ( is_a( $skin, 'SkinBlueSpiceEclipseSkin', true ) ) {
							return new EnhancedGlobalActionsFarmManagement( $this->titleFactory );
						}
						return new GlobalActionsFarmManagement( $this->titleFactory );
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

		$title = $context->getTitle();
		if (
			$title &&
			$title->isSpecial( 'Wikis' ) &&
			is_a( $skin, 'SkinBlueSpiceEclipseSkin', true )
		) {
			$registry->register(
				'TitleActions', [
					'create-wiki' => [
						'factory' => function () {
							return new CreateInstanceButton( $this->permissionManager, $this->spf, $this->countLimiter );
						}
					]
				]
			);
		}
	}

}
