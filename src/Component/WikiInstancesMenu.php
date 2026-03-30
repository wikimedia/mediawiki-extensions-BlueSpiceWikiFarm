<?php

namespace BlueSpice\WikiFarm\Component;

use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCard;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdownIcon;
use MWStake\MediaWiki\Component\CommonUserInterface\IRestrictedComponent;

class WikiInstancesMenu extends SimpleDropdownIcon implements IRestrictedComponent {

	/** @var Config */
	private $farmConfig;

	/**
	 * @param Config $farmConfig
	 */
	public function __construct( Config $farmConfig ) {
		parent::__construct( [] );
		$this->farmConfig = $farmConfig;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'farm-wikis-btn';
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		return $this->farmConfig->get( 'showInstancesMenu' ) && $this->farmConfig->get( 'shareUsers' );
	}

	/**
	 * @return array
	 */
	public function getContainerClasses(): array {
		return [ 'has-megamenu' ];
	}

	/**
	 * @return array
	 */
	public function getButtonClasses(): array {
		return [ 'ico-btn', 'wikifarm-instances-btn' ];
	}

	/**
	 * @return array
	 */
	public function getMenuClasses(): array {
		return [ 'megamenu' ];
	}

	/**
	 * @return array
	 */
	public function getIconClasses(): array {
		return [ 'bi-bs-wiki-instances' ];
	}

	/**
	 * @return Message
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-instances-menu-button-title' );
	}

	/**
	 * @return Message
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-instances-menu-aria-label' );
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		return [
			new SimpleCard( [
				'id' => 'farm-wikis-mm',
				'classes' => [ 'mega-menu', 'd-flex', 'justify-content-center' ],
				'items' => []
			] ),
			// literal for transparent megamenu container
			new Literal(
				'farm-wikis-mm-div',
				'<div id="farm-wikis-mm-div" class="mm-bg"></div>'
			)
		];
	}

	/**
	 *
	 * @return array
	 */
	public function getPermissions(): array {
		return [ 'read' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredRLModules(): array {
		return [
			'ext.bluespice.wikiFarm.instances.megamenu'
		];
	}

}
