<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\AccessControl\GroupListStore;
use MWStake\MediaWiki\Component\Utils\UtilityFactory;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class PopulateGroupList extends \MediaWiki\Maintenance\LoggedUpdateMaintenance {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doDBUpdates() {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || FARMER_IS_ROOT_WIKI_CALL === false ) {
			// Only run on root wiki
			return true;
		}
		/** @var UtilityFactory $utilsFactory */
		$utilsFactory = $this->getServiceContainer()->getService( 'MWStakeCommonUtilsFactory' );
		$groupHelper = $utilsFactory->getGroupHelper();
		$groups = $groupHelper->getAvailableGroups( [
			'filter' => [ 'custom' ]
		] );
		/** @var GroupListStore $groupListStore */
		$groupListStore = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm._GroupListStore' );
		$groupListStore->setGroups( $groups );
		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-populate-group-list';
	}
}

$maintClass = PopulateGroupList::class;
require_once RUN_MAINTENANCE_IF_MAIN;
