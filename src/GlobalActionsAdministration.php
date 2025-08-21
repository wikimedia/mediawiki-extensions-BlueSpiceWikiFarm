<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\RestrictedTextLink;

class GlobalActionsAdministration extends RestrictedTextLink {

	public function __construct() {
		parent::__construct( [] );
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return 'ga-bs-farmmanagement';
	}

	/**
	 * @return array
	 */
	public function getPermissions(): array {
		return [ 'wikifarm-managewiki' ];
	}

	/**
	 * @return string
	 */
	public function getHref(): string {
		$tool = SpecialPage::getTitleFor( 'Farm_management' );
		return $tool->getLocalURL();
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return Message::newFromKey( 'wikifarm-farmmanagement-text' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-farmmanagement-desc' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-farmmanagement-text' );
	}
}
