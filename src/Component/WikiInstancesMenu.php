<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\ColorUtils;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCard;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdownButton;
use MWStake\MediaWiki\Component\CommonUserInterface\IRestrictedComponent;
use Wikimedia\HtmlArmor\HtmlArmor;

class WikiInstancesMenu extends SimpleDropdownButton implements IRestrictedComponent {

	/** @var InstanceEntity */
	private $instance = null;

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
		return 'farm-wikis-btn';
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		if ( !$this->farmConfig->get( 'showInstancesMenu' ) && !$this->farmConfig->get( 'shareUsers' ) ) {
			return false;
		}
		$instance = $this->instanceStore->getCurrentInstance();
		if ( !$instance ) {
			return false;
		}
		$this->instance = $instance;
		return true;
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
		return Message::newFromKey( 'wikifarm-instances-menu-button-aria-label',
			$this->instance->getDisplayName() );
	}

	/**
	 * @inheritDoc
	 */
	public function getSubComponents(): array {
		$components = [
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
		return $components;
	}

	/**
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

	/**
	 * @inheritDoc
	 */
	public function getText(): Message {
		$full = $this->instance->getDisplayName();
		return new RawMessage( $full );
	}

	/**
	 * @inheritDoc
	 */
	public function getPreHtml(): HtmlArmor {
		$color = ( new ColorUtils() )->getInstanceForeground( $this->instance );
		return new HtmlArmor( $this->buildInstanceLabelHtml( $color ) );
	}

	/**
	 * @param string $color
	 * @return string
	 */
	private function buildInstanceLabelHtml( string $color ): string {
		$pathPrefix = $this->instance->getPath();
		if ( $this->instance instanceof RootInstanceEntity ) {
			return Html::element( 'span', [
				'class' => 'bi-bs-main-wiki',
				'style' => "color: $color;"
			] );
		}
		return Html::element( 'span', [
			'class' => 'wikifarm-instance-label-path',
			'style' => "color: $color;",
			'aria-hidden' => 'true'
		], $pathPrefix );
	}

}
