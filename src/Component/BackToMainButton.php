<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleLink;
use MWStake\MediaWiki\Component\CommonUserInterface\IRestrictedComponent;

class BackToMainButton extends SimpleLink implements IRestrictedComponent {

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 */
	public function __construct( private readonly Config $farmConfig,
		private readonly InstanceStore $instanceStore ) {
		parent::__construct( [] );
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'farm-back-to-main-btn';
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		if ( !$this->farmConfig->get( 'showInstancesMenu' ) ) {
			return false;
		}

		if ( !FARMER_IS_ROOT_WIKI_CALL ) {
			return true;
		}
		return false;
	}

	/**
	 * @return array
	 */
	public function getClasses(): array {
		return [ 'ico-btn', 'bi-bs-main-wiki' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getRole(): string {
		return 'link';
	}

	/**
	 * @return Message
	 */
	public function getText(): Message {
		return new RawMessage( '' );
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-back-to-main-wiki-btn-title' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-back-to-main-wiki-btn-aria-label' );
	}

	/**
	 * @inheritDoc
	 */
	public function getHref(): string {
		$mainInstance = $this->instanceStore->getInstanceByPath( 'w' );
		return '/' . $mainInstance->getPath();
	}

	/**
	 * @return array
	 */
	public function getPermissions(): array {
		return [ 'read' ];
	}

}
