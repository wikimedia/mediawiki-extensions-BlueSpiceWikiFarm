<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Maintenance\CreateAccessToken;
use BlueSpice\WikiFarm\Maintenance\CreateSystemInstances;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class RunDatabaseUpdates implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @inheritDoc
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$dbType = $updater->getDB()->getType();
		$dir = $GLOBALS['IP'] . '/extensions/BlueSpiceWikiFarm';

		if ( defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && FARMER_IS_ROOT_WIKI_CALL ) {
			// TODO: Dont create for instances
			$updater->addExtensionTable(
				'simple_farmer_instances',
				"$dir/db/$dbType/simple_farmer_instances.sql"
			);

			$updater->addExtensionTable(
				'simple_farmer_processes',
				"$dir/db/$dbType/simple_farmer_processes.sql"
			);
		}

		// Table should exist on "shared user db", which might not be farm root
		$updater->addExtensionTable(
			'wikifarm_session_cache',
			"$dir/db/$dbType/wikifarm_session_cache.sql"
		);

		$updater->addExtensionTable(
			'wiki_teams',
			"$dir/db/$dbType/wiki_teams.sql"
		);
		$updater->addExtensionTable(
			'wiki_team_roles',
			"$dir/db/$dbType/wiki_team_roles.sql"
		);

		$updater->addPostDatabaseUpdateMaintenance( CreateSystemInstances::class );
		$updater->addPostDatabaseUpdateMaintenance( CreateAccessToken::class );
	}
}
