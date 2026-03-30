<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Rest\SimpleHandler;

class PinnedInstanceHandler extends SimpleHandler {

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly InstanceStore $instanceStore
	) {
	}

	public function execute() {
		$instances = $this->instanceStore->getPinnedInstances();
		$pinned = [];
		foreach ( $instances as $instance ) {
			$pinned[] = [
				'path' => $instance->getPath(),
				'title' => $instance->getDisplayName(),
				'meta_desc' => $instance->getMetadata()['description'],
				'hasFavouriteIcon' => false,
				'isFavourite' => false,
				'fullurl' => $instance->getUrl( $this->farmConfig ),
				'instance_color' => $instance->getMetadata()['instanceColor']['background']
			];
		}
		return $this->getResponseFactory()->createJson( $pinned );
	}
}
