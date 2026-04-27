<?php

namespace BlueSpice\WikiFarm\BreadcrumbRootProvider;

use BlueSpice\Discovery\BreadcrumbRootProvider\BaseBreadcrumbRootProvider;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Title\Title;

class SharedInstancesRootNode extends BaseBreadcrumbRootProvider {

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 * @param SpecialPageFactory $specialPageFactory
	 */
	public function __construct( private readonly Config $farmConfig,
		private readonly InstanceStore $instanceStore,
		private readonly SpecialPageFactory $specialPageFactory ) {
			parent::__construct( $specialPageFactory );
	}

	/**
	 * @param Title $title
	 * @return array
	 */
	public function getNodes( Title $title ): array {
		$nsNode = parent::getNodes( $title );
		$nodes = [];

		$activeInstance = $this->instanceStore->getCurrentInstance();
		if ( !$activeInstance ) {
			return $nsNode;
		}

		if ( $activeInstance->getId() !== 'w' ) {
			$mainInstance = $this->instanceStore->getInstanceByPath( 'w' );
			$instanceName = $activeInstance->getPath();

			$nodes[] = [
				'text' => '',
				'href' => $mainInstance->getUrl( $this->farmConfig ),
				'title' => Message::newFromKey( 'wikifarm-breadcrumb-nav-back-to-main-title' )->text(),
				'rootNode-class' => [ 'instance-root-home' ],
				'rootNode-link-class' => [ 'bi-bs-home' ],
				'aria-label' => Message::newFromKey( 'wikifarm-breadcrumb-nav-back-to-main-title' )->text()
			];

			$instanceNode = [
				'text' => str_replace( '_', ' ', $instanceName ),
				'href' => $activeInstance->getUrl( $this->farmConfig ),
				'title' => $activeInstance->getDisplayName(),
				'rootNode-class' => [ 'instance-rootnode' ]
			];
			if ( $activeInstance->getMetadata()['instanceColor'] ) {
				$colorConfig = $activeInstance->getMetadata()['instanceColor'];
				$bgColor = $colorConfig['background'];
				$fgColor = '#000';
				if ( $colorConfig['lightText'] ) {
					$fgColor = '#fff';
				}
				$instanceNode['style'] = [ 'background-color:' . $bgColor . ';color:' . $fgColor . ';' ];
			}
			$nodes[] = $instanceNode;
		} else {
			$nodes[] = [
				'text' => '',
				'href' => $activeInstance->getUrl( $this->farmConfig ),
				'title' => Message::newFromKey( 'wikifarm-breadcrumb-nav-back-to-main-page-title' )->text(),
				'rootNode-class' => [ 'instance-root-home' ],
				'rootNode-link-class' => [ 'bi-bs-home' ],
				'aria-label' => Message::newFromKey( 'wikifarm-breadcrumb-nav-back-to-main-page-title' )->text()
			];
		}

		$nodes = array_merge( $nodes, $nsNode );
		return $nodes;
	}

	/**
	 *
	 * @param Title $title
	 * @return bool
	 */
	public function applies( Title $title ): bool {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			return true;
		}
		return false;
	}

}
