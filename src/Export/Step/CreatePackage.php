<?php

namespace BlueSpice\WikiFarm\Export\Step;

use BlueSpice\WikiFarm\Event\ExportPackageReadyEvent;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\User\UserFactory;
use MediaWiki\Utils\MWTimestamp;
use MWStake\MediaWiki\Component\Events\Notifier;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageTransaction;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Throwable;
use ZipArchive;

class CreatePackage extends ExportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param UserFactory $userFactory
	 * @param Notifier $notifier
	 * @param string $loggerName
	 * @param string $instanceId
	 * @param string $actorName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		private readonly UserFactory $userFactory,
		private readonly Notifier $notifier,
		string $loggerName,
		private readonly string $instanceId,
		private readonly ?string $actorName
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws Throwable
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager, $this->instanceId );

		$manifestFiles = $this->getManifestFiles();
		$manifestFiles[] = [
			'file' => 'manifest.json',
			'path' => null
		];

		$timestampNow = MWTimestamp::getInstance();
		$packageName = "{$instance->getPath()}-{$timestampNow->format( 'YmdHis' )}.bluespice";
		$packageTempPath = $this->getExportDirectory();
		$packagePath = $this->storage->getTempFilePath( $packageName, $packageTempPath, true );
		$zip = new ZipArchive();
		$openResult = $zip->open( $packagePath, ZipArchive::CREATE | ZipArchive::OVERWRITE );
		if ( $openResult !== true ) {
			throw new RuntimeException( "Failed to open package archive for writing: $packagePath" );
		}

		$zipSourceFiles = [];
		try {
			foreach ( $manifestFiles as $manifestFile ) {
				$filename = $manifestFile['file'] ?? '';
				$path = $manifestFile['path'] ?
					$this->getExportDirectory() . '/' . $manifestFile['path'] : $this->getExportDirectory();
				$file = $this->storage->getTempFile( $filename, $path );
				if ( !$file ) {
					throw new RuntimeException( "Failed to retrieve export file for packaging: $path/$filename" );
				}
				$zipSourceFiles[] = $file;

				$relativePath = $this->buildPackageEntryPath( $filename, $path );
				$added = $zip->addFile( $file->getPath(), $relativePath );
				if ( !$added ) {
					throw new RuntimeException( "Failed to add file to package: $relativePath" );
				}
			}
		} catch ( Throwable $e ) {
			$zip->close();
			throw $e;
		}

		if ( !$zip->close() ) {
			throw new RuntimeException( "Failed to finalize package archive: $packagePath" );
		}
		unset( $zipSourceFiles );

		$instanceStorageBackend = $this->storage->getBackend(
			$this->instanceManager->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		$status = ( new StorageTransaction( $instanceStorageBackend ) )
			->setContainer( 'export-import' )
			->store( $packagePath, $packageName, '' )
			->commit();
		$this->validateStatus( $status );

		$cleanupStatus = $this->storage->newTempTransaction()
			->delete( $packageName, $packageTempPath )
			->commit();
		if ( !$cleanupStatus->isOK() ) {
			$this->logger->warning( 'CreatePackage: failed to remove temporary package file from temp storage', [
				'file' => $packageName,
				'path' => $packageTempPath,
			] );
		}

		$downloadUrl = $this->buildPackageDownloadUrl( $packageName );
		$this->addToData( 'packageFile', $packageName );
		$this->addToData( 'packageContainer', 'export-import' );
		$this->addToData( 'packageDownloadUrl', $downloadUrl );

		$this->logger->info( 'Created package "{package}"', [ 'package' => $packageName ] );

		if ( $this->actorName ) {
			$actor = $this->userFactory->newFromName( $this->actorName );
			if ( $actor && $actor->isRegistered() ) {
				$event = new ExportPackageReadyEvent(
					$actor,
					$instance->getDisplayName(),
					$packageName,
					$downloadUrl,
					$this->getMainChecksum()
				);
				$this->notifier->emit( $event );
			}
		}
	}

	/**
	 * @param string $filename
	 * @param string $path
	 * @return string
	 */
	private function buildPackageEntryPath( string $filename, string $path ): string {
		$exportDir = trim( $this->getExportDirectory(), '/' );
		$normalizedPath = trim( $path, '/' );
		if ( $normalizedPath === $exportDir ) {
			return $filename;
		}
		if ( str_starts_with( $normalizedPath, $exportDir . '/' ) ) {
			return substr( $normalizedPath, strlen( $exportDir ) + 1 ) . '/' . $filename;
		}
		if ( $normalizedPath === '' ) {
			return $filename;
		}
		return $normalizedPath . '/' . $filename;
	}

	/**
	 * @param string $packageName
	 * @return string
	 */
	private function buildPackageDownloadUrl( string $packageName ): string {
		$rootInstance = new RootInstanceEntity();
		$baseUrl = $rootInstance->getUrl( $this->instanceManager->getFarmConfig() );

		return $baseUrl . '/rest.php/bluespice/farm/v1/export/package/' . rawurlencode( $packageName );
	}
}
