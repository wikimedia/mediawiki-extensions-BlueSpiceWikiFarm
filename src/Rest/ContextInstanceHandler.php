<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Message;

class ContextInstanceHandler extends SimpleHandler {

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly InstanceStore $instanceStore
	) {
	}

	/**
	 * @return Response
	 */
	public function execute() {
		$mainInstance = $this->instanceStore->getInstanceByPath( 'w' );
		$quickAccess[] = [
			'path' => $mainInstance->getPath(),
			'title' => $mainInstance->getDisplayName(),
			'fullurl' => $mainInstance->getUrl( $this->farmConfig ),
			'iconClass' => 'bi-bs-home',
			'classes' => 'farm-wiki-instance-main'
		];
		if ( $this->farmConfig->get( 'useSharedResources' ) ) {
			$sharedInstance = $this->instanceStore->getInstanceByPath(
				$this->farmConfig->get( 'sharedResourcesWikiPath' ) );
			$sharedInstanceUrl = $sharedInstance?->getUrl( $this->farmConfig );
			$quickAccess[] = [
				'path' => $sharedInstance->getPath(),
				'title' => Message::newFromKey( 'wikifarm-shared-instance-name' )->text(),
				'fullurl' => $sharedInstanceUrl,
				'iconClass' => 'bi-bs-shared',
				'classes' => 'farm-wiki-instance-shared'
			];
		}
		$activeInstance = $this->instanceStore->getCurrentInstance();
		if ( !$activeInstance ) {
			return;
		}
		$metadata = $activeInstance->getMetadata();
		$current[] = [
			'path' => $activeInstance->getPath(),
			'title' => $activeInstance->getDisplayName(),
			'meta_desc' => $metadata['description'],
			'hasFavouriteIcon' => false,
			'isFavourite' => false,
			'fullurl' => $activeInstance->getUrl( $this->farmConfig ),
			'instance_color' => $metadata['instanceColor']['background']
		];

		$context[ 'current' ] = $current;
		$context[ 'central' ] = $quickAccess;
		return $this->getResponseFactory()->createJson( $context );
	}
}
