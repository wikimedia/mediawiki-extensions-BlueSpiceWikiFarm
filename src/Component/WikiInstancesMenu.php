<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCard;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCardBody;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleCardHeader;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\SimpleDropdownIcon;
use MWStake\MediaWiki\Component\CommonUserInterface\IRestrictedComponent;

class WikiInstancesMenu extends SimpleDropdownIcon implements IRestrictedComponent {

	/** @var InstanceStore */
	private $instanceStore;

	/** @var Config */
	private $farmConfig;

	/** @var UserOptionsLookup */
	private $userOptionsLookup;

	/**
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( InstanceStore $instanceStore, Config $farmConfig, UserOptionsLookup $userOptionsLookup ) {
		parent::__construct( [] );
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->userOptionsLookup = $userOptionsLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function getId(): string {
		return 'farm-wikis-btn';
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
		return [ 'ico-btn' ];
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
		return [ 'bi-compass-fill' ];
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
		$cardBodyItems = [];
		$user = RequestContext::getMain()->getUser();
		if ( !$user->isAnon() ) {
			$favoriteCard = new SimpleCard( [
				'id' => 'farm-wikis-favorite',
				'classes' => [ 'card-mn' ],
				'items' => [
					new SimpleCardHeader( [
						'id' => 'farm-wikis-favorite-head',
						'classes' => [ 'menu-title' ],
						'items' => [
							new Literal(
								'farm-wikis-favorite-menu-title',
								Message::newFromKey(
									'wikifarm-instances-menu-favorite-text'
								)
							)
						]
					] ),
					new Literal(
						'farm-wikis-favorite-items',
						$this->getFavoriteWikisHtml()
					)
				]
			] );
			$cardBodyItems[] = $favoriteCard;
		}

		$overviewCard = new SimpleCard( [
			'id' => 'farm-wikis-overview',
			'classes' => [ 'card-mn' ],
			'items' => [
				new SimpleCardHeader( [
					'id' => 'farm-wikis-overview-head',
					'classes' => [ 'menu-title' ],
					'items' => [
						new Literal(
							'farm-wikis-overview-menu-title',
							Message::newFromKey(
								'wikifarm-instances-menu-overview-text'
							)
						)
					]
				] ),
				new Literal(
					'farm-wikis-overview-items',
					$this->getOverviewWikisHtml()
				)
			]
		] );

		$cardBodyItems[] = $overviewCard;

		$mainCard = new SimpleCard( [
			'id' => 'farm-wikis-mm',
			'classes' => [ 'mega-menu', 'd-flex', 'justify-content-center' ],
			'items' => [
				new SimpleCardBody( [
					'id' => 'farm-wikis-megamn-body',
					'classes' => [ 'd-flex', 'mega-menu-wrapper' ],
					'items' => $cardBodyItems
				] )
			]
		] );

		return [
			$mainCard,
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
	 * @return string
	 */
	private function getOverviewWikisHtml(): string {
		$instances = $this->instanceStore->getAllInstances();
		$html = '';

		foreach ( $instances as $instance ) {
			if ( !$instance->isActive() ) {
				continue;
			}
			$html .= $this->getWikiInstanceCard( $instance );
		}

		return $html;
	}

	/**
	 * @return array
	 */
	private function getFavouriteWikisForCurrentUser(): array {
		$user = RequestContext::getMain()->getUser();
		$favouriteOptions = $this->userOptionsLookup->getOption( $user, 'bs-farm-instances-favorite' );
		if ( !$favouriteOptions ) {
			return [];
		}
		$favourites = explode( ',', $favouriteOptions );
		return $favourites;
	}

	/**
	 * @return string
	 */
	private function getFavoriteWikisHtml(): string {
		$favourites = $this->getFavouriteWikisForCurrentUser();
		$html = '';
		if ( empty( $favourites ) ) {
			$html = Html::element( 'p', [],
				Message::newFromKey( 'wikifarm-instances-menu-empty-favorite-text' )->text() );
			return $html;
		}
		$instances = $this->instanceStore->getAllInstances();

		foreach ( $instances as $instance ) {
			if ( !in_array( $instance->getPath(), $favourites ) ) {
				continue;
			}
			$html .= $this->getWikiInstanceCard( $instance );
		}

		return $html;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string
	 */
	private function getWikiInstanceCard( $instance ): string {
		$cardHtml = Html::openElement( 'div', [
			'class' => 'farm-wiki-card-item',
			'data-path' => $instance->getPath()
		] );

		$classes = 'farm-wiki-card-favorite-btn';
		$favourites = $this->getFavouriteWikisForCurrentUser();
		if ( in_array( $instance->getPath(), $favourites ) ) {
			$classes .= ' bi-star-fill';
		} else {
			$classes .= ' bi-star';
		}
		$cardHtml .= Html::element( 'div', [ 'class' => $classes ] );

		$cardHtml .= Html::openElement( 'div', [ 'class' => 'farm-wiki-card-desc' ] );
		$cardHtml .= Html::element( 'a', [
			'href' => $instance->getUrl( $this->farmConfig )
		], $instance->getDisplayName() );
		$cardHtml .= Html::closeElement( 'div' );

		$cardHtml .= Html::closeElement( 'div' );
		return $cardHtml;
	}

}
