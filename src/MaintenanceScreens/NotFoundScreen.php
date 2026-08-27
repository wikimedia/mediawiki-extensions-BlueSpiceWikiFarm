<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

/**
 * Shown instead of the generic "Permission error"/Badtitle page when a user
 * has no read access to the wiki instance they are trying to visit. Presents itself
 * as "not found" so the existence of the wiki is not disclosed
 */
class NotFoundScreen extends MaintenanceScreen {

	/**
	 * @var Authority
	 */
	private $authority;

	/**
	 * @var Title|null
	 */
	private $requestedTitle;

	/**
	 * @var InterwikiLookup
	 */
	private $interwikiLookup;

	/**
	 * @param InstanceEntity $instanceEntity
	 * @param Authority $authority
	 * @param InterwikiLookup $interwikiLookup
	 * @param Title|null $requestedTitle
	 */
	public function __construct(
		InstanceEntity $instanceEntity, Authority $authority,
		InterwikiLookup $interwikiLookup, ?Title $requestedTitle = null
	) {
		parent::__construct( $instanceEntity );
		$this->authority = $authority;
		$this->interwikiLookup = $interwikiLookup;
		$this->requestedTitle = $requestedTitle;
	}

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'not-found';
	}

	/**
	 * @return int|null
	 */
	public function getRefreshInterval(): ?int {
		return null;
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey( 'wikifarm-not-found-title' )->text();
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		$isNamed = $this->authority->getUser()->isNamed();
		$links = $this->getActionLinks( $isNamed );
		return [
			'instance_name' => $this->instanceEntity->getDisplayName(),
			'message' => Message::newFromKey(
				$isNamed ? 'wikifarm-not-found-text' : 'wikifarm-not-found-text-anon'
			)->text(),
			'actions' => $links ? [ 'links' => $links ] : false
		];
	}

	/**
	 * @param bool $isNamed
	 * @return array
	 */
	private function getActionLinks( bool $isNamed ): array {
		$links = [];
		if ( !$isNamed ) {
			$query = [];
			if ( $this->requestedTitle ) {
				$query['returnto'] = $this->requestedTitle->getPrefixedText();
			}
			$links[] = [
				'url' => SpecialPage::getTitleFor( 'Userlogin' )->getFullURL( $query ),
				'label' => Message::newFromKey( 'wikifarm-not-found-login' )->text()
			];
		}
		$isRoot = $this->instanceEntity instanceof RootInstanceEntity;
		$mainWikiUrl = $isNamed && !$isRoot ? $this->getMainWikiUrl() : null;
		if ( $mainWikiUrl ) {
			$links[] = [
				'url' => $mainWikiUrl,
				'label' => Message::newFromKey( 'wikifarm-not-found-mainwiki' )->text()
			];
		}
		if ( $isNamed ) {
			// Without this there is no way to switch to an account that does have access
			$links[] = [
				'url' => SpecialPage::getTitleFor( 'Userlogout' )->getFullURL(),
				'label' => Message::newFromKey( 'wikifarm-not-found-logout' )->text()
			];
		}
		return $links;
	}

	/**
	 * URL of the main wiki, derived from the 'w' interwiki prefix the farm sets up on every
	 * instance. An empty target yields the article path root, which resolves to the main page
	 *
	 * @return string|null
	 */
	private function getMainWikiUrl(): ?string {
		$interwiki = $this->interwikiLookup->fetch( 'w' );
		if ( !$interwiki ) {
			return null;
		}
		return preg_replace( '#(?<!:)//#', '/', $interwiki->getURL( '' ) );
	}
}
