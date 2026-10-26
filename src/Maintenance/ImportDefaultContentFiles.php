<?php

namespace BlueSpice\WikiFarm\Maintenance;

use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

/**
 * Imports the files used by the default main page of a new wiki (`content/mainpage/files`).
 * The pages themselves are created by the installer (`InstanceCliInstaller` for instances,
 * `BsCliInstaller` of bluespice/mw-config-overrides for the root wiki), but the installer
 * does not load the wiki's configuration, so it can not know where uploads are stored.
 */
class ImportDefaultContentFiles extends LoggedUpdateMaintenance {

	/**
	 * @return bool
	 */
	protected function doDBUpdates() {
		$services = $this->getServiceContainer();
		$repo = $services->getRepoGroup()->getLocalRepo();
		$backlinkCacheFactory = $services->getBacklinkCacheFactory();
		$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );

		foreach ( glob( dirname( __DIR__, 2 ) . '/content/mainpage/files/*' ) as $path ) {
			$title = Title::makeTitleSafe( NS_FILE, basename( $path ) );
			$file = $repo->newFile( $title );
			if ( $file->exists() ) {
				continue;
			}
			if ( !$backlinkCacheFactory->getBacklinkCache( $title )->hasLinks( 'imagelinks' ) ) {
				continue;
			}

			$this->output( "Importing {$title->getText()}..." );
			$status = $file->upload( $path, '', '', 0, false, false, $user );
			$this->output( $status->isOK() ? " done.\n" : " failed: {$status->getMessage()->text()}\n" );
		}
		return true;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-import-default-content-files';
	}
}

$maintClass = ImportDefaultContentFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
