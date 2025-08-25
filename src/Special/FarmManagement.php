<?php

namespace BlueSpice\WikiFarm\Special;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstanceTemplateProvider;
use BlueSpice\WikiFarm\SystemInstanceEntity;
use Html;
use MediaWiki\Language\LanguageCode;
use MediaWiki\Languages\LanguageNameUtils;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionStatus;
use SpecialPage;

class FarmManagement extends SpecialPage {

	/**
	 * @param LanguageNameUtils $languageNameUtils
	 * @param InstanceCountLimiter $countLimiter
	 * @param InstanceManager $instanceManager
	 */
	public function __construct(
		private readonly LanguageNameUtils $languageNameUtils,
		private readonly InstanceCountLimiter $countLimiter,
		private readonly InstanceManager $instanceManager
	) {
		parent::__construct( 'FarmManagement', 'wikifarm-managewiki' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );

		if ( !$this->isRootCall() ) {
			$this->getOutput()->showErrorPage(
				$this->msg( 'wikifarm-management-not-available-title' ),
				$this->msg( 'wikifarm-management-not-available-text' )
			);
			return;
		}

		$this->getOutput()->addJsConfigVars(
			'wgWikiFarmAvailableLanguages',
			$this->getLanguages()
		);
		if ( $subPage === '_create' ) {
			// Creating
			if ( !$this->countLimiter->canCreate() ) {
				$this->getOutput()->showPermissionStatus(
					PermissionStatus::newFatal( 'wikifarm-error-instance-limit-reached' )
				);
				return;
			}
			$this->getOutput()->setPageTitle( $this->msg( 'wikifarm-create-instance-title' ) );
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
			$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.special.bootstrap' ] );

			$createParams = [
				'name' => $this->getRequest()->getText( 'name' ),
				'template' => $this->getRequest()->getText( 'template' ),
				'path' => $this->getRequest()->getText( 'path' ),
				'globalAccessEnabled' => $this->instanceManager->getFarmConfig()->get( 'useGlobalAccessControl' ),
				'templates' => ( new InstanceTemplateProvider( $this->getConfig() ) )->getTemplates()
			];
			if ( $createParams['template'] === 'clone' ) {
				$source = $this->getVerifiedSource();
				if ( $source ) {
					$createParams['source'] = $source;
				} else {
					unset( $createParams['template'] );
				}
			}
			$this->getOutput()->addHTML(
				Html::element( 'div', [
					'id' => 'farm-create-instance',
					'class' => 'row justify-content-md-center',
					'data-params' => json_encode( $createParams )
				] )
			);
		} elseif ( $subPage ) {
			// Editing
			$instance = $this->instanceManager->getStore()->getInstanceByIdOrPath( $subPage );
			if ( !$instance ) {
				$this->getOutput()->showErrorPage(
					'wikifarm-instance-not-found', 'wikifarm-instance-not-found-desc',
					[ $subPage ], $this->getPageTitle()
				);
				return;
			}
			$this->getOutput()->setPageTitle( $instance->getDisplayName() );
			$this->getOutput()->addBacklinkSubtitle( $this->getPageTitle() );
			$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.special.bootstrap' ] );
			$this->getOutput()->addHTML(
				Html::element( 'div', [
					'id' => 'farm-edit-instance',
					'class' => 'row justify-content-md-center',
					'data-instance' => json_encode(
						$instance->dbSerialize() + [ 'is_system' => $instance instanceof SystemInstanceEntity ]
					),
				] )
			);
		} else {
			// Overview
			if ( $this->countLimiter->isLimited() ) {
				$this->getOutput()->addJsConfigVars(
					'wgWikiFarmInstanceLimit',
					[
						'active' => $this->countLimiter->getCurrentActiveCount(),
						'limit' => $this->countLimiter->getLimit(),
					]
				);
			}
			$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.management' ] );
			$this->getOutput()->addHTML(
				Html::element( 'div', [ 'id' => 'farm-management' ] )
			);
		}
	}

	/**
	 * @return string|null
	 */
	private function getVerifiedSource(): ?string {
		$source = trim( $this->getRequest()->getText( 'source' ) );
		if ( !$source ) {
			return null;
		}
		$entity = $this->instanceManager->getStore()->getInstanceByIdOrPath( $source );
		if ( !$entity ) {
			return null;
		}
		return $entity->getPath();
	}

	/**
	 * @return bool
	 */
	private function isRootCall(): bool {
		return defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && FARMER_IS_ROOT_WIKI_CALL;
	}

	/**
	 * @return array
	 */
	private function getLanguages(): array {
		$languages = $this->languageNameUtils->getLanguageNames(
			LanguageNameUtils::AUTONYMS,
			LanguageNameUtils::SUPPORTED
		);
		$languageCode = $this->getConfig()->get( MainConfigNames::LanguageCode );
		if ( !array_key_exists( $languageCode, $languages ) ) {
			$languages[$languageCode] = $languageCode;
			ksort( $languages );
		}

		$options = [];
		foreach ( $languages as $code => $name ) {
			$display = LanguageCode::bcp47( $code ) . ' - ' . $name;
			$options[] = [
				'data' => $code,
				'label' => $display
			];
		}
		return $options;
	}

}
