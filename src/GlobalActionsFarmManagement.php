<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;

class GlobalActionsFarmManagement extends RestrictedTextLink {

	public function __construct() {
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
		$tool = SpecialPage::getTitleFor( 'Farm_management' );
		return $tool->getLocalURL();
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
