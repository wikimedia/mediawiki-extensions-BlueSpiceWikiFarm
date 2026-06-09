<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use MediaWiki\Context\IContextSource;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleLink;

class CreateInstanceButton extends SimpleLink {

	/**
	 * @param PermissionManager $permissionManager
	 * @param SpecialPageFactory $spf
	 * @param InstanceCountLimiter $countLimiter
	 */
	public function __construct( private readonly PermissionManager $permissionManager,
		private readonly SpecialPageFactory $spf, private readonly InstanceCountLimiter $countLimiter ) {
		return parent::__construct( [] );
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'create-instance-btn';
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getClasses(): array {
		return [ 'ca-new-wiki', 'ico-btn', 'bi-bs-create-page' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getRole(): string {
		return 'button';
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-wikis-create-new-wiki-title' );
	}

	/**
	 * @inheritDoc
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-wikis-create-new-wiki-aria-label' );
	}

	/**
	 * @inheritDoc
	 */
	public function getHref(): string {
		$sp = $this->spf->getPage( 'FarmManagement' );
		if ( !$sp ) {
			return '';
		}
		return $sp->getPageTitle( '_create' )->getLocalURL();
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		if ( !$this->countLimiter->canCreate() ) {
			return false;
		}
		$user = $context->getUser();
		$userCan = $this->permissionManager->userHasRight( $user, 'wikifarm-createwiki' );
		if ( $userCan ) {
			return true;
		}
		return false;
	}
}
