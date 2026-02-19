<?php

namespace BlueSpice\WikiFarm\Hook\SkinTemplateNavigation;

use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Title\TitleFactory;

class RewriteUserPageLink implements SkinTemplateNavigation__UniversalHook {

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
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && FARMER_IS_ROOT_WIKI_CALL ) {
			return;
		}

		if ( !isset( $links['user-menu']['userpage'] ) ) {
			return;
		}

		$user = $sktemplate->getUser();
		if ( !$user->isRegistered() ) {
			return;
		}

		$rootUserUrl = $this->buildRootUserUrl( $user->getName() );
		if ( !$rootUserUrl ) {
			return;
		}

		$links['user-menu']['userpage']['href'] = $rootUserUrl;
		$links['user-menu']['userpage']['target'] = '_self';
	}

	/**
	 * @param string $username
	 * @return string|null
	 */
	private function buildRootUserUrl( string $username ): ?string {
		$title = $this->titleFactory->newFromText( "w:User:$username" );
		if ( !$title ) {
			return null;
		}

		return $title->getFullUrl();
	}

}
