<?php

namespace BlueSpice\WikiFarm\Data\UnifiedGroupStore;

use BlueSpice\WikiFarm\AccessControl\GroupListStore;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\HookContainer\HookContainer;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\GroupStore\Reader as GroupReader;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use MWStake\MediaWiki\Component\Utils\Utility\GroupHelper;

/**
 * @stable to extend
 */
class Reader extends GroupReader {

	/**
	 * @param GroupHelper $groupHelper
	 * @param GlobalVarConfig $mwsgConfig
	 * @param HookContainer $hookContainer
	 * @param GroupListStore $groupListStore
	 */
	public function __construct(
		GroupHelper $groupHelper, GlobalVarConfig $mwsgConfig,
		HookContainer $hookContainer, private readonly GroupListStore $groupListStore
	) {
		parent::__construct( $groupHelper, $mwsgConfig, $hookContainer );
	}

	/**
	 * @param ReaderParams $params
	 *
	 * @return PrimaryDataProvider
	 */
	public function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider(
			$this->groupHelper, $this->mwsgConfig, $this->hookContainer, $this->groupListStore
		);
	}
}
