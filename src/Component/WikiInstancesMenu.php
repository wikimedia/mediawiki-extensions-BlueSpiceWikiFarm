<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\Context\RequestContext;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;
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

	/** @var GroupAccessStore */
	private $accessControlStore;

	/** @var array */
	private $favourites;

	/** @var int */
	protected const MENU_LIMIT = 10;

	/**
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param UserOptionsLookup $userOptionsLookup
	 * @param IAccessStore $accessControlStore
	 */
	public function __construct( InstanceStore $instanceStore, Config $farmConfig,
		UserOptionsLookup $userOptionsLookup, IAccessStore $accessControlStore ) {
		parent::__construct( [] );
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->userOptionsLookup = $userOptionsLookup;
		$this->accessControlStore = $accessControlStore;

		$this->favourites = [];
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
		$cardBodyItems = [];
		$user = RequestContext::getMain()->getUser();
		if ( !$user->isAnon() ) {
			$this->favourites = $this->getFavouriteWikisForCurrentUser();
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
						$this->getFavoriteWikisHtml( $user )
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
					$this->getOverviewWikisHtml( $user )
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
	 * @param User $user
	 * @return string
	 */
	private function getOverviewWikisHtml( $user ): string {
		$instances = $this->accessControlStore->getInstancePathsWhereUserHasRole( $user, 'reader' );

		$html = $this->getMainWikiInstanceCard();
		$count = 1;

		foreach ( $instances as $instancePath ) {
			if ( in_array( $instancePath, $this->favourites ) ) {
				continue;
			}
			if ( $count >= $this::MENU_LIMIT ) {
				break;
			}
			if ( $instancePath === 'w' ) {
				continue;
			}
			$instance = $this->instanceStore->getInstanceByPath( $instancePath );
			if ( !$instance->isActive() ) {
				continue;
			}

			$html .= $this->getWikiInstanceCard( $instance );
			$count++;
		}
		if ( count( $instances ) > $this::MENU_LIMIT ) {
			$html .= $this->getInstanceLink( 'all', Message::newFromKey( 'wikifarm-all-wikis-link' ) );
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
	 * @param User $user
	 * @return string
	 */
	private function getFavoriteWikisHtml( $user ): string {
		$html = '';
		if ( empty( $this->favourites ) ) {
			$html = Html::element( 'p', [],
				Message::newFromKey( 'wikifarm-instances-menu-empty-favorite-text' )->text() );
			return $html;
		}
		$instances = $this->accessControlStore->getInstancePathsWhereUserHasRole( $user, 'reader' );

		$count = 0;
		foreach ( $instances as $instancePath ) {
			if ( !in_array( $instancePath, $this->favourites ) ) {
				continue;
			}
			if ( $count >= $this::MENU_LIMIT ) {
				break;
			}
			$instance = $this->instanceStore->getInstanceByPath( $instancePath );
			$html .= $this->getWikiInstanceCard( $instance );
			$count++;
		}

		if ( count( $instances ) > $this::MENU_LIMIT ) {
			$html .= $this->getInstanceLink( 'favourites', Message::newFromKey( 'wikifarm-favorite-wikis-link' ) );
		}

		return $html;
	}

	/**
	 * @param string $name
	 * @param string $text
	 * @return string
	 */
	private function getInstanceLink( $name, $text ) {
		$sp = SpecialPage::getTitleFor( 'Wikis' );
		$html = Html::openElement( 'div', [
			'class' => 'farm-wiki-instance-overview'
		] );
		$html .= Html::element( 'a', [
			'id' => 'wikifarm-instance-page-' . $name,
			'href' => $sp->getLocalURL() . '#' . $name
		], $text );
		$html .= Html::closeElement( 'div' );
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
		$titleMsgKey = 'wikifarm-instances-favorite-add-btn-title-label';
		if ( in_array( $instance->getPath(), $favourites ) ) {
			$classes .= ' bi-bs-favored wiki-instance-favored';
			$titleMsgKey = 'wikifarm-instances-favorite-remove-btn-title-label';
		} else {
			$classes .= ' bi-bs-unfavored';
		}
		$cardHtml .= Html::openElement( 'div', [] );
		$cardHtml .= Html::element( 'a', [
			'class' => $classes,
			'role' => 'button',
			'title' => Message::newFromKey( $titleMsgKey )->text()
		] );
		$cardHtml .= Html::closeElement( 'div' );

		$cardHtml .= Html::openElement( 'div', [ 'class' => 'farm-wiki-card-desc' ] );
		$cardHtml .= Html::element( 'a', [
			'href' => $instance->getUrl( $this->farmConfig )
		], $instance->getDisplayName() );
		$cardHtml .= Html::closeElement( 'div' );

		$cardHtml .= Html::closeElement( 'div' );
		return $cardHtml;
	}

	/**
	 * @return string
	 */
	private function getMainWikiInstanceCard(): string {
		$mainInstance = new RootInstanceEntity();
		$cardHtml = Html::openElement( 'div', [
			'class' => 'farm-wiki-card-item farm-wiki-instance-main',
			'data-path' => $mainInstance->getPath()
		] );

		$cardHtml .= Html::element( 'span', [
			'class' => 'bi-bs-home'
		] );

		$cardHtml .= Html::openElement( 'div', [ 'class' => 'farm-wiki-card-desc' ] );
		$cardHtml .= Html::element( 'a', [
			'href' => $mainInstance->getUrl( $this->farmConfig )
		], $mainInstance->getDisplayName() );
		$cardHtml .= Html::closeElement( 'div' );

		$cardHtml .= Html::closeElement( 'div' );
		return $cardHtml;
	}

}
