<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Setup;
use BlueSpice\WikiFarm\Util\InstanceDisplayRecordHelper;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\SimpleHandler;
use MWStake\MediaWiki\Component\DataStash\StashManager;
use Wikimedia\ParamValidator\ParamValidator;

class GetLastVisitedInstances extends SimpleHandler {

	/**
	 * @param InstanceDisplayRecordHelper $instanceDisplayRecordHelper
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 * @param StashManager $stashManager
	 */
	public function __construct(
		private readonly InstanceDisplayRecordHelper $instanceDisplayRecordHelper,
		private readonly IAccessStore $accessStore,
		private readonly InstanceStore $instanceStore,
		private readonly StashManager $stashManager
	) {
	}

	/**
	 * @return array
	 */
	public function execute() {
		$user = RequestContext::getMain()->getUser();
		$lastVisited = $this->stashManager->getGlobal( Setup::LAST_VISITED_STASH_KEY, $user ) ?? [];
		if ( !$lastVisited ) {
			return [];
		}

		$limit = $this->getValidatedParams()['limit'];
		if ( $limit < 1 ) {
			$limit = 10;
		}
		$lastVisited = array_slice( $lastVisited, 0, $limit );
		$data = [];
		$instances = $this->instanceStore->getMultiple( 'sfi_path', $lastVisited );
		foreach ( $instances as $instance ) {
			if ( $this->accessStore->userHasRoleOnInstance( $user, IAccessStore::ROLE_READER, $instance ) ) {
				$record = $this->instanceDisplayRecordHelper->getDisplayRecord( $instance, $user );
				if ( $record ) {
					$data[] = $record;
				}
			}
		}

		return $data;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'limit' => [
				static::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_DEFAULT => 10,
			]
		];
	}
}
