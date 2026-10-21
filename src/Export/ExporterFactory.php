<?php

namespace BlueSpice\WikiFarm\Export;

use BlueSpice\WikiFarm\Export\Step\Cleanup;
use BlueSpice\WikiFarm\Export\Step\CreateManifest;
use BlueSpice\WikiFarm\Export\Step\CreatePackage;
use BlueSpice\WikiFarm\Export\Step\ExportDatabaseData;
use BlueSpice\WikiFarm\Export\Step\ExportDatabaseSchema;
use BlueSpice\WikiFarm\Export\Step\ExportImages;
use BlueSpice\WikiFarm\Export\Step\PrepareTarget;
use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;

class ExporterFactory {

	public function getSteps( InstanceEntity $instance, ?UserIdentity $actor ): array {
		return [
			'prepare-target' => [
				'class' => PrepareTarget::class,
				'services' => [ 'MWStake.StorageUtilities', 'FormatterFactory', 'ContentLanguage' ],
				'args' => [ 'BlueSpiceWikiFarm.Export', $instance->getId(), $actor->getName() ]
			],
			'export-db-schema' => [
				'class' => ExportDatabaseSchema::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager',
					'BlueSpiceWikiFarm.Util.DoctrineDatabaseConnection',
					'MWStake.StorageUtilities',
					'FormatterFactory',
					'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Export', $instance->getId() ]
			],
			'export-db-data' => [
				'class' => ExportDatabaseData::class,
				'services' => [
					'HookContainer',
					'BlueSpiceWikiFarm.InstanceManager',
					'BlueSpiceWikiFarm.Util.DoctrineDatabaseConnection',
					'MWStake.StorageUtilities',
					'FormatterFactory',
					'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Export', $instance->getId() ]
			],
			'export-images' => [
				'class' => ExportImages::class,
				'services' => [
					'HookContainer',
					'BlueSpiceWikiFarm.InstanceManager',
					'MWStake.StorageUtilities',
					'FormatterFactory',
					'ContentLanguage'
				],
				'args' => [ 'BlueSpiceWikiFarm.Export', $instance->getId() ]
			],
			'create-manifest' => [
				'class' => CreateManifest::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MWStake.StorageUtilities',
					'FormatterFactory', 'ContentLanguage', 'HookContainer',
					'BlueSpiceWikiFarm.ManagementDatabaseFactory'
				],
				'args' => [ 'BlueSpiceWikiFarm.Export', $instance->getId() ],
			],
			'create-package' => [
				'class' => CreatePackage::class,
				'services' => [
					'BlueSpiceWikiFarm.InstanceManager', 'MWStake.StorageUtilities',
					'FormatterFactory', 'ContentLanguage', 'UserFactory', 'MWStake.Notifier'
				],
				'args' => [ 'BlueSpiceWikiFarm.Export', $instance->getId(), $actor?->getName() ],
			],
			'cleanup' => [
				'class' => Cleanup::class,
				'services' => [ 'MWStake.StorageUtilities', 'FormatterFactory', 'ContentLanguage' ],
				'args' => [ 'BlueSpiceWikiFarm.Export' ]
			]
		];
	}

	/**
	 * @param InstanceEntity $instance
	 * @param UserIdentity $actor
	 * @return ManagedProcess
	 */
	public function getProcess( InstanceEntity $instance, UserIdentity $actor ): ManagedProcess {
		return new ManagedProcess( $this->getSteps( $instance, $actor ), timeout: 3600 );
	}
}
