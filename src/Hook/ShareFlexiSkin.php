<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Config\Config;
use MediaWiki\Extension\FlexiSkin\FlexiSkin;
use MediaWiki\Extension\FlexiSkin\FlexiSkinAdaptConfigurationHook;
use MediaWiki\Extension\FlexiSkin\FlexiSkinGetActiveSkinHook;
use MediaWiki\Extension\FlexiSkin\FlexiSkinManager;
use MediaWiki\FileRepo\File\LocalFile;
use MediaWiki\FileRepo\RepoGroup;

class ShareFlexiSkin implements FlexiSkinGetActiveSkinHook, FlexiSkinAdaptConfigurationHook {

	/** @var FlexiSkin|null */
	private ?FlexiSkin $mainWikiSkin = null;

	/**
	 * @param Config $farmConfig
	 * @param RepoGroup $repoGroup
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly RepoGroup $repoGroup
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onFlexiSkinGetActiveSkin( FlexiSkinManager $manager, ?FlexiSkin &$skin ): void {
		$this->mainWikiSkin = $manager->getSkinForWiki( $this->farmConfig->get( 'rootInstanceWikiId' ) );
		$skin = $this->mainWikiSkin;
	}

	/**
	 * @inheritDoc
	 */
	public function onFlexiSkinAdaptConfiguration( FlexiSkin $skin, array &$config ): void {
		$globalServer = $this->farmConfig->get( 'globalServer' );
		$uploadPath = $this->farmConfig->get( 'globalUploadPath' );
		$uploadPath = is_string( $uploadPath ) ? trim( $uploadPath, '/' ) : '';

		foreach ( $config['images'] as &$data ) {
			if ( $data['source'] !== $this->farmConfig->get( 'rootInstanceWikiId' ) ) {
				continue;
			}
			if ( empty( $data['filename'] ) ) {
				continue;
			}
			// Here we are assuming file exits and setting it blindly, as are probably not in the root instance context
			$file = new LocalFile( $data['filename'], $this->repoGroup->getLocalRepo() );
			$data['url'] = "$globalServer/$uploadPath/{$file->getRel()}";
		}
	}
}
