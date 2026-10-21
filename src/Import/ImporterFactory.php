<?php

namespace BlueSpice\WikiFarm\Import;

use BlueSpice\WikiFarm\Import\Step\Cleanup;
use BlueSpice\WikiFarm\Import\Step\CreateDatabase;
use BlueSpice\WikiFarm\Import\Step\CreateInstance;
use BlueSpice\WikiFarm\Import\Step\ImportDatabaseData;
use BlueSpice\WikiFarm\Import\Step\ImportImages;
use BlueSpice\WikiFarm\Import\Step\RunPostImportScripts;
use BlueSpice\WikiFarm\Import\Step\SetInstanceReady;
use BlueSpice\WikiFarm\Import\Step\ValidatePackage;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;

class ImporterFactory {

	/**
	 * @param string $packageName
	 * @return array[]
	 */
	public function getSteps( string $packageName ): array {
		return [
			'verify-package' => [
				'class' => ValidatePackage::class,
				'services' => [
					'BlueSpiceWikiFarm.Import.PackageValidator',
					'MWStake.StorageUtilities', 'FormatterFactory', 'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import', $packageName ]
			],
			'create-instance' => [
				'class' => CreateInstance::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MWStake.StorageUtilities',
					'FormatterFactory', 'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import' ]
			],
			'create-database' => [
				'class' => CreateDatabase::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager',
					'BlueSpiceWikiFarm.ManagementDatabaseFactory',
					'BlueSpiceWikiFarm.Util.DoctrineDatabaseConnection',
					'MWStake.StorageUtilities',
					'FormatterFactory',
					'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import' ]
			],
			'import-data' => [
				'class' => ImportDatabaseData::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MWStake.StorageUtilities', 'FormatterFactory', 'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import' ]
			],
			'import-images' => [
				'class' => ImportImages::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MWStake.StorageUtilities', 'FormatterFactory', 'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import' ]
			],
			'run-post-import-scripts' => [
				'class' => RunPostImportScripts::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MainConfig', 'HookContainer', 'MWStake.StorageUtilities',
					'FormatterFactory', 'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import' ]
			],
			'set-instance-ready' => [
				'class' => SetInstanceReady::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MWStake.StorageUtilities',
					'FormatterFactory', 'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import' ]
			],
			'cleanup' => [
				'class' => Cleanup::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager',
					'BlueSpiceWikiFarm.Import.PackageValidator',
					'MWStake.StorageUtilities',
					'FormatterFactory',
					'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Import', $packageName ]
			]
		];
	}

	/**
	 * @param string $packageName
	 * @return ManagedProcess
	 */
	public function getProcess( string $packageName ): ManagedProcess {
		return new ManagedProcess( $this->getSteps( $packageName ), timeout: 3600 );
	}
}
