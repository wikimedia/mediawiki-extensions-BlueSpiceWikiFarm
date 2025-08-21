<?php

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

class MigrateInstancesToDatabase extends Maintenance {

	/**
	 * @var InstanceManager
	 */
	private $manager;

	/**
	 * @inheritDoc
	 */
	public function __construct() {
		parent::__construct();

		$this->addOption( 'dry', 'Show instances to be migrated' );
	}

	public function execute() {
		$this->manager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );

		$oldInstances = $this->getFileSystemInstances(
			$this->getDBInstanceNames(),
			$this->manager->getFarmConfig()->get( 'instanceDirectory' )
		);

		foreach ( $oldInstances as $key => $data ) {
			$this->output( ">>> $key <<<\n" );
			$this->output( json_encode( $data['entity']->dbSerialize(), JSON_PRETTY_PRINT ) . "\n\n" );
			if ( $this->hasOption( 'dry' ) ) {
				continue;
			}
			$this->manager->getStore()->store( $data['entity'] );
			// Move LocalSettings
			rename( $data['file'], $data['migratedLocalSettingsFile'] );
			$this->output( "Migrated\n" );
		}
	}

	/**
	 * Get all instance names from the file system
	 *
	 * @return array
	 * @throws Exception
	 */
	private function getFileSystemInstances( array $dbInstances, string $dir ) {
		$iterator = new DirectoryIterator( $dir );
		$instances = [];
		foreach ( $iterator as $fileInfo ) {
			if ( $fileInfo->isFile() || $fileInfo->isDot() ) {
				continue;
			}

			$instanceName = $fileInfo->getBasename();
			if ( in_array( $instanceName, $dbInstances ) ) {
				continue;
			}

			$settings = $this->getSettings( $fileInfo );
			if ( !isset( $settings['wgDBname'] ) ) {
				$this->error( "Instance $instanceName has no database name" );
				continue;
			}
			$meta = $this->getMeta( $fileInfo );
			$createDate = $this->getCreateDate( $fileInfo );
			$instanceEntity = new InstanceEntity(
				$this->manager->getStore()->generateId(),
				$instanceName,
				$settings['wgSitename'] ?? $instanceName,
				$createDate,
				$createDate,
				InstanceEntity::STATUS_READY,
				$settings['wgDBname'],
				$settings['wgDBprefix'] ?? '',
				[
					'group' => $meta['group'] ?? '',
					'keywords' => $meta['keywords'] ?? [],
					'desc' => $meta['desc'] ?? '',
				],
				[]
			);
			$instances[$instanceName] = [
				'entity' => $instanceEntity,
				'file' => $fileInfo->getPathname() . '/LocalSettings.php',
				'migratedLocalSettingsFile' => $fileInfo->getPathname() . '/LocalSettings.migrated.php',
			];
		}
		return $instances;
	}

	/**
	 * @param SplFileInfo $fileInfo
	 * @return array
	 */
	private function getSettings( $fileInfo ): array {
		$settingsFile = $fileInfo->getPathname() . '/LocalSettings.php';
		if ( file_exists( $settingsFile ) ) {
			$sreader = new \BlueSpice\WikiFarm\SettingsReader( $settingsFile );
			return $sreader->getArray();
		}
		return [];
	}

	/**
	 * @param SplFileInfo $fileInfo
	 * @return array
	 */
	private function getMeta( $fileInfo ): array {
		$metaFile = $fileInfo->getPathname() . '/meta.json';
		if ( file_exists( $metaFile ) ) {
			return json_decode( file_get_contents( $metaFile ), true );
		}
		return [];
	}

	/**
	 * @param SplFileInfo $fileInfo
	 * @return DateTime
	 * @throws Exception
	 */
	private function getCreateDate( $fileInfo ): DateTime {
		$file = $fileInfo->getPathname() . '/CREATEDATE';
		if ( file_exists( $file ) ) {
			$ts = file_get_contents( $file );
			return new DateTime( '@' . $ts );
		}
		return new DateTime();
	}

	/**
	 * @return array
	 */
	private function getDBInstanceNames(): array {
		$instances = $this->manager->getStore()->getInstanceIds();
		$names = [];
		foreach ( $instances as $id ) {
			$instance = $this->manager->getStore()->getInstanceById( $id );
			if ( !$instance ) {
				continue;
			}
			$names[] = $instance->getPath();
		}
		return $names;
	}

}

$maintClass = 'MigrateInstancesToDatabase';
require_once RUN_MAINTENANCE_IF_MAIN;
