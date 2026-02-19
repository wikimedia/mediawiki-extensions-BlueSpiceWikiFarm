<?php

namespace BlueSpice\WikiFarm\Hook\BeforeInitialize;

use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Title\TitleFactory;

class RedirectUserPage implements BeforeInitializeHook {

	/** @var TitleFactory */
	private $titleFactory;

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct( TitleFactory $titleFactory ) {
		$this->titleFactory = $titleFactory;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize(
		$title,
		$unused,
		$output,
		$user,
		$request,
		$mediaWikiEntryPoint
	) {
		if ( defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && FARMER_IS_ROOT_WIKI_CALL ) {
			return;
		}

		if ( $title->getNamespace() !== NS_USER ) {
			return;
		}

		$rootUserUrl = $this->buildRootUserUrl( $title->getPrefixedDBkey() );
		if ( !$rootUserUrl ) {
			return;
		}

		$output->redirect( $rootUserUrl );
	}

	/**
	 * @param string $userPageTitleKey
	 * @return string|null
	 */
	private function buildRootUserUrl( string $userPageTitleKey ): ?string {
		$title = $this->titleFactory->newFromText( "w:$userPageTitleKey" );
		if ( !$title ) {
			return null;
		}

		return $title->getFullUrl();
	}

}
