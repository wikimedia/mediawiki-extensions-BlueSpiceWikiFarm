<?php

namespace BlueSpice\WikiFarm\Export\Step;

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use JsonException;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use MWStake\MediaWiki\Component\SchemaVersion\SchemaVersionManager;
use RuntimeException;

class CreateManifest extends ExportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param HookContainer $hookContainer
	 * @param ManagementDatabaseFactory $managementDatabaseFactory
	 * @param string $loggerName
	 * @param string $instanceId
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		private readonly HookContainer $hookContainer,
		private readonly ManagementDatabaseFactory $managementDatabaseFactory,
		string $loggerName, private readonly string $instanceId
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager, $this->instanceId );

		$config = $this->censorConfig( $instance->getConfig() );
		$instanceData = [
			'id' => $instance->getId(),
			'path' => $instance->getPath(),
			'name' => $instance->getDisplayName(),
			'meta' => $instance->getMetadata(),
			'config' => $config,
			'dbname' => $instance->getDbName(),
			'dbprefix' => $instance->getDbPrefix(),
		];

		$manifestFiles = $this->getManifestFiles();
		foreach ( $manifestFiles as &$manifestFile ) {
			$file = $this->storage->getTempFile(
				filename: $manifestFile['file'],
				path: $manifestFile['path'] ?
					$this->getExportDirectory() . '/' . $manifestFile['path'] : $this->getExportDirectory()
			);
			$content = $file ? file_get_contents( $file->getPath() ) : false;
			if ( !$file || $content === false ) {
				$this->logger->error( 'CreateManifest: Failed to retrieve required file', [
					'instanceId' => $this->getInstanceId(),
					'file' => $manifestFile['file'],
					'path' => $manifestFile['path'],
				] );
				throw new RuntimeException( Message::newFromKey( 'wikifarm-export-error-verify-file-failed' )->text() );
			}

			$checksum = md5( $content );
			$manifestFile['checksum'] = $checksum;
		}

		$schemaVersionManager = new SchemaVersionManager( $this->hookContainer );
		$managementDb = $this->managementDatabaseFactory->getManagementConnection();
		$schemaVersion = $schemaVersionManager->getCurrentVersion( $managementDb, $instance->getWikiId() );
		$manifestData = array_merge( [
			'instanceData' => $instanceData,
			'files' => $manifestFiles,
			'env' => $this->getEnv(),
			'schemaVersion' => $schemaVersion ? [
				'hash' => $schemaVersion['hash'],
				'timestamp' => $schemaVersion['timestamp'],
			] : null,
		], $this->getManifestInfo() );

		$mainChecksum = md5( json_encode( $manifestData, JSON_THROW_ON_ERROR ) );
		$manifestData['mainChecksum'] = $mainChecksum;
		$this->addToData( 'mainChecksum', $mainChecksum );

		$status = $this->storage->newTempTransaction()
			->create(
				filename: 'manifest.json',
				content: json_encode( $manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ),
				path: $this->getExportDirectory()
			)->commit();

		$this->validateStatus( $status );
	}

	/**
	 * @param array $config
	 * @return array
	 */
	private function censorConfig( array $config ): array {
		// TODO: Allow hooking into here?
		unset( $config['access_token'] );
		return $config;
	}

	/**
	 * @return array
	 */
	private function getEnv(): array {
		$configs = [
			'wgHashedUploadDirectory' => $GLOBALS['wgHashedUploadDirectory'],
		];
		if ( isset( $GLOBALS['wgAWSRepoHashLevels'] ) && $GLOBALS['wgAWSRepoHashLevels'] > 0 ) {
			$configs['wgAWSRepoHashLevels'] = $GLOBALS['wgAWSRepoHashLevels'];
		}
		if ( isset( $GLOBALS['wgAWSRepoDeletedHashLevels'] ) && $GLOBALS['wgAWSRepoDeletedHashLevels'] > 0 ) {
			$configs['wgAWSRepoDeletedHashLevels'] = $GLOBALS['wgAWSRepoDeletedHashLevels'];
		}
		return $configs;
	}
}
