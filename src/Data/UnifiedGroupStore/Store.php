<?php

namespace BlueSpice\WikiFarm\Data\UnifiedGroupStore;

use BlueSpice\WikiFarm\AccessControl\GroupListStore;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\HookContainer\HookContainer;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\GroupStore\Store as GroupStore;
use MWStake\MediaWiki\Component\Utils\UtilityFactory;

/*
 * This store is only used for AccessManagement
 */
class Store extends GroupStore {

	/**
	 * @param UtilityFactory $utilityFactory
	 * @param GlobalVarConfig $mwsgConfig
	 * @param HookContainer $hookContainer
	 * @param GroupListStore $groupListStore
	 */
	public function __construct(
		UtilityFactory $utilityFactory, GlobalVarConfig $mwsgConfig, HookContainer $hookContainer,
		private readonly GroupListStore $groupListStore
	) {
		parent::__construct( $utilityFactory, $mwsgConfig, $hookContainer );
	}

	/**
	 * @return \MWStake\MediaWiki\Component\DataStore\Reader
	 */
	public function getReader() {
		return new Reader( $this->groupHelper, $this->mwsgConfig, $this->hookContainer, $this->groupListStore );
	}
}
