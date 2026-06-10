<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Message\Message;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;

class GlobalActionsFarmManagement extends RestrictedTextLink {

	/**
	 * @param TitleFactory $titleFactory
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory
	) {
		parent::__construct( [] );
	}

	/** @inheritDoc */
	public function getId(): string {
		return 'ga-bs-farmmanagement';
	}

	/** @inheritDoc */
	public function getPermissions(): array {
		return [ 'wikifarm-managewiki' ];
	}

	/** @inheritDoc */
	public function getHref(): string {
		if ( FARMER_IS_ROOT_WIKI_CALL ) {
			$title = $this->titleFactory->makeTitle( NS_SPECIAL, 'Farm_management' );
			return $title->getLocalURL();
		}
		$title = $this->titleFactory->newFromText( 'w:Special:Farm_management' );
		return $title->getFullURL();
	}

	/** @inheritDoc */
	public function getText(): Message {
		return Message::newFromKey( 'wikifarm-farmmanagement-text' );
	}

	/** @inheritDoc */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-farmmanagement-desc' );
	}

	/** @inheritDoc */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-farmmanagement-text' );
	}
}
