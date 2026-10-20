<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleButtonGroup;
use MWStake\MediaWiki\Component\CommonUserInterface\IRestrictedComponent;

class WikiInstancesButtonGroup extends SimpleButtonGroup implements IRestrictedComponent {

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 */
	public function __construct( Config $farmConfig, private readonly InstanceStore $instanceStore ) {
		parent::__construct( [
			'id' => 'farm-wikis-button-group',
			'aria-label' => Message::newFromKey( 'wikifarm-navigation-aria-label' ),
			'items' => [
				new BackToMainButton( $farmConfig, $instanceStore ),
				new WikiInstancesMenu( $farmConfig, $instanceStore )
			]
		] );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		foreach ( $this->getSubComponents() as $component ) {
			if ( $component->shouldRender( $context ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getClasses(): array {
		$classes = [ 'wikifarm-instances-button-group' ];
		$instance = $this->instanceStore->getCurrentInstance();
		if ( $instance ) {
			$classes[] = $instance->getPath() . '-background';
		}
		return $classes;
	}

	/**
	 * @inheritDoc
	 */
	public function getRole(): string {
		return 'group';
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredRLStyles(): array {
		return [ 'ext.bluespice.wikiFarm.navigation' ];
	}

	/**
	 * @inheritDoc
	 */
	public function getPermissions(): array {
		return [ 'read' ];
	}
}
