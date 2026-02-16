<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\InstanceStore;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class PopulateWikiId extends \MediaWiki\Maintenance\LoggedUpdateMaintenance {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doDBUpdates() {
		if ( !FARMER_IS_ROOT_WIKI_CALL ) {
			// Only run on root wiki
			return true;
		}
		/** @var InstanceStore $instanceStore */
		$instanceStore = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceStore' );
		foreach ( $instanceStore->getAllInstances() as $instance ) {
			// Just re-storing it will update wikiId
			$instanceStore->store( $instance );
		}

		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-populate-wiki-map';
	}
}

$maintClass = CreateSystemInstances::class;
require_once RUN_MAINTENANCE_IF_MAIN;
