<?php

namespace BlueSpice\WikiFarm\Data\UnifiedGroupStore;

use BlueSpice\WikiFarm\AccessControl\GroupListStore;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\HookContainer\HookContainer;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\GroupStore\PrimaryDataProvider as PrimaryGroupProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\Utils\Utility\GroupHelper;

/*
 * @stable to extend
 */
class PrimaryDataProvider extends PrimaryGroupProvider {

	/**
	 * @param GroupHelper $groupHelper
	 * @param GlobalVarConfig $mwsgConfig
	 * @param HookContainer $hookContainer
	 * @param GroupListStore $groupListStore
	 */
	public function __construct(
		GroupHelper $groupHelper,
		GlobalVarConfig $mwsgConfig,
		HookContainer $hookContainer,
		private readonly GroupListStore $groupListStore
	) {
		parent::__construct( $groupHelper, $mwsgConfig, $hookContainer );
	}

	/**
	 * @param ReaderParams $params
	 * @return array
	 */
	protected function getGroupNames( ReaderParams $params ): array {
		return $this->groupListStore->getGroups();
	}
}
