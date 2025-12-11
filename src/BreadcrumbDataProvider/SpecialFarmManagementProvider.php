<?php

namespace BlueSpice\WikiFarm\BreadcrumbDataProvider;

use BlueSpice\Discovery\BreadcrumbDataProvider\BaseBreadcrumbDataProvider;
use MediaWiki\Language\RawMessage;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;
use RuntimeException;

class SpecialFarmManagementProvider extends BaseBreadcrumbDataProvider {

	private string $wikiName;
	private string $actionName;

	/**
	 * @param SpecialPageFactory $specialPageFactory
	 * @param TitleFactory $titleFactory
	 * @param MessageLocalizer $messageLocalizer
	 * @param WebRequestValues $webRequestValues
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct( private SpecialPageFactory $specialPageFactory,
		$titleFactory, $messageLocalizer, $webRequestValues, $namespaceInfo ) {
		parent::__construct( $titleFactory, $messageLocalizer, $webRequestValues, $namespaceInfo );
		$this->actionName = '';
		$this->wikiName = '';
	}

	/**
	 * @param Title $title
	 * @return Title
	 * @throws RuntimeException
	 */
	public function getRelevantTitle( $title ): Title {
		$specialPage = $this->specialPageFactory->getPage( 'FarmManagement' );
		if ( !$specialPage ) {
			throw new RuntimeException( 'The "FarmManagement" page doesn\'t exist' );
		}
		$specialPageTitle = $specialPage->getPageTitle();
		if ( !isset( $this->webRequestValues['title'] ) ) {
			return $specialPageTitle;
		}

		$requestTitle = $this->webRequestValues['title'];
		$bits = explode( '/', $requestTitle );
		if ( count( $bits ) === 1 ) {
			return $specialPageTitle;
		}
		$subpage = array_pop( $bits );
		if ( $subpage === '_create' ) {
			$this->actionName = 'create';
		} else {
			$this->wikiName = $subpage;
		}

		return $specialPageTitle;
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getLabels( $title ): array {
		$labels = [];
		if ( $this->actionName ) {
			$msgSpecialKey = 'wikifarm-management-breadcrumb-label-' . strtolower( $this->actionName );
			$msgSpecialText = $this->messageLocalizer->msg( $msgSpecialKey );
			if ( !$msgSpecialText->exists() ) {
				$msgSpecialText = new RawMessage( $this->actionName );
			}
			$labels[] = [
				'text' => $msgSpecialText
			];
		}
		if ( $this->wikiName ) {
			$labels[] = [
				'text' => $this->wikiName
			];
		}

		return $labels;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function applies( Title $title ): bool {
		return $title->isSpecial( 'FarmManagement' );
	}

	/**
	 *
	 * @inheritDoc
	 */
	public function isSelfLink( $node ): bool {
		if ( $this->actionName || $this->wikiName ) {
			return false;
		}
		return true;
	}
}
