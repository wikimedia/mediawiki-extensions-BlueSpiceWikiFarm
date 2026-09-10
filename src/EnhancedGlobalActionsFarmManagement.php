<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\TitleFactory;

class EnhancedGlobalActionsFarmManagement extends GlobalActionsFarmManagement {

	/**
	 * @param TitleFactory $titleFactory
	 * @param InstanceStore $instanceStore
	 */
	public function __construct( TitleFactory $titleFactory,
		private readonly InstanceStore $instanceStore ) {
		return parent::__construct( $titleFactory );
	}

	/** @inheritDoc */
	public function getHref(): string {
		if ( FARMER_IS_ROOT_WIKI_CALL ) {
			$title = $this->titleFactory->makeTitle( NS_SPECIAL, 'Farm_management' );
			return $title->getLocalURL();
		}
		$contextTitle = RequestContext::getMain()->getTitle();
		$title = $this->titleFactory->newFromText( 'w:Special:Farm_management' );
		$instance = $this->instanceStore->getCurrentInstance();
		$link = $instance->getPath() . ':' . $contextTitle->getFullText();
		return $title->getLocalURL( 'backTo=wiki-' . $link );
	}
}
