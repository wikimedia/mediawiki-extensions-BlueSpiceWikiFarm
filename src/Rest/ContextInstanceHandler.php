<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Message;

class ContextInstanceHandler extends SimpleHandler {

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 * @param IAccessStore $accessStore
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly InstanceStore $instanceStore,
		private readonly IAccessStore $accessStore

	) {
	}

	/**
	 * @return Response
	 */
	public function execute() {
		if ( !$this->accessStore instanceof GroupAccessStore ) {
			return $this->getResponseFactory()->createJson( [] );
		}
		$user = RequestContext::getMain()->getUser();
		$paths = $this->accessStore->getInstancePathsWhereUserHasRole( $user, 'reader' );

		$mainInstance = $this->instanceStore->getInstanceByPath( 'w' );
		$quickAccess = [];
		if ( !in_array( $mainInstance->getPath(), $paths ) ) {
			$quickAccess[] = [
				'path' => $mainInstance->getPath(),
				'title' => $mainInstance->getDisplayName(),
				'fullurl' => $mainInstance->getUrl( $this->farmConfig ),
				'iconClass' => 'bi-bs-home',
				'classes' => 'farm-wiki-instance-main'
			];
		}

		if ( $this->farmConfig->get( 'useSharedResources' ) ) {
			$sharedInstance = $this->instanceStore->getInstanceByPath(
				$this->farmConfig->get( 'sharedResourcesWikiPath' ) );
			if ( in_array( $sharedInstance->getPath(), $paths ) ) {
				$sharedInstanceUrl = $sharedInstance?->getUrl( $this->farmConfig );
				$quickAccess[] = [
					'path' => $sharedInstance->getPath(),
					'title' => Message::newFromKey( 'wikifarm-shared-instance-name' )->text(),
					'fullurl' => $sharedInstanceUrl,
					'iconClass' => 'bi-bs-shared',
					'classes' => 'farm-wiki-instance-shared'
				];
			}
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
