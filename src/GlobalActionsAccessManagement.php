<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;

class GlobalActionsAccessManagement extends RestrictedTextLink {

	public function __construct() {
		parent::__construct( [] );
	}

	/** @inheritDoc */
	public function getId(): string {
		return 'ga-bs-accessmanagement';
	}

	/** @inheritDoc */
	public function getPermissions(): array {
		return [ 'userrights' ];
	}

	/** @inheritDoc */
	public function getHref(): string {
		$tool = SpecialPage::getTitleFor( 'AccessManagement' );
		return $tool->getLocalURL();
	}

	/** @inheritDoc */
	public function getText(): Message {
		return Message::newFromKey( 'wikifarm-accessmanagement-text' );
	}

	/** @inheritDoc */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-accessmanagement-desc' );
	}

	/** @inheritDoc */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-accessmanagement-text' );
	}
}
