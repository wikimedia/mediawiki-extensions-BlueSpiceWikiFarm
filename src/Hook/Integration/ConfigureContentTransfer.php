<?php

namespace BlueSpice\WikiFarm\Hook\Integration;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Hook\MediaWikiServicesHook;
use MediaWiki\Languages\Data\Names;
use MediaWiki\MediaWikiServices;
use MediaWiki\Registration\ExtensionRegistry;

class ConfigureContentTransfer implements MediaWikiServicesHook {

	/** * @var bool */
	private bool $isConfigured = false;

	/**
	 * @inheritDoc
	 */
	public function onMediaWikiServices( $services ): void {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'ContentTransfer' ) ) {
			return;
		}
		$services->addServiceManipulator(
			'ContentTransferTargetManager',
			function ( $original, MediaWikiServices $services ) {
				if ( $this->isConfigured ) {
					return null;
				}
				$this->setupContentTransfer( $services );
				$this->isConfigured = true;
				return null;
			} );
	}

	/**
	 * @param MediaWikiServices $services
	 * @return void
	 */
	private function setupContentTransfer( MediaWikiServices $services ) {
		if ( MW_ENTRY_POINT !== 'index' && MW_ENTRY_POINT !== 'api' && MW_ENTRY_POINT !== 'rest' ) {
			return;
		}

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'OAuth' ) ) {
			return;
		}

		$farmConfig = $services->getService( 'BlueSpiceWikiFarm._Config' );
		$internalServer = $farmConfig->get( 'internalServer' );

		if ( !is_array( $GLOBALS['wgContentTransferTargets'] ) ) {
			$GLOBALS['wgContentTransferTargets'] = [];
		}
		if ( !is_array( $GLOBALS['bsgTranslateTransferNamespaces'] ?? [] ) ) {
			$GLOBALS['bsgTranslateTransferNamespaces'] = [];
		}
		foreach ( $GLOBALS['wgWikiFarmGlobalStore']->getAllInstances() as $instance ) {
			if ( !$instance->isActive() ) {
				continue;
			}
			$path = $instance->getPath();
			if ( $this->isLanguageCode( $path ) && $this->isLanguageCode( FARMER_CALLED_INSTANCE ) ) {
				$GLOBALS['bsgTranslateTransferTargets'][strtolower( $path )] = [
					'key' => $path,
					'url' => $instance->getUrl( $farmConfig ) . '/wiki'
				];

				$GLOBALS['bsgTranslateTransferNamespaces'][strtolower( $path )] = [ NS_MAIN ];
			}
			if ( $path === FARMER_CALLED_INSTANCE ) {
				// Do not set up current instance
				continue;
			}

			$accessToken = $this->getInstanceAccessToken( $instance );
			if ( !$accessToken ) {
				continue;
			}

			$apiUrl = $internalServer . $instance->getScriptPath( $farmConfig ) . '/api.php';
			$GLOBALS['wgContentTransferTargets'][$instance->getPath()] = [
				'url' => $apiUrl,
				'access_token' => $accessToken,
				'pushToDraft' => false,
				'displayText' => $instance->getDisplayName(),
				'draftNamespace' => 'Draft'
			];
		}

		// Set up root wiki language as "leading language" for BlueSpiceTranslationTransfer
		$GLOBALS['bsgTranslateTransferLeadingLanguage'] = FARMER_ROOT_WIKI_LANGUAGE_CODE;
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function isLanguageCode( string $path ): bool {
		$names = Names::NAMES;
		return isset( $names[strtolower( $path )] );
	}

	/**
	 * @param InstanceEntity $instanceEntity
	 * @return string|null
	 */
	public function getInstanceAccessToken( InstanceEntity $instanceEntity ): ?string {
		$instanceConfig = $instanceEntity->getConfig();
		if ( isset( $instanceConfig['access_token'] ) ) {
			return $instanceConfig['access_token'];
		}
		return null;
	}
}
