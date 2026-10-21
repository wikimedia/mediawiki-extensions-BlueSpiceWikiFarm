<?php

namespace BlueSpice\WikiFarm\Export\Step;

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Storage\InstanceTransaction;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Throwable;

class ExportImages extends ExportStep implements InterruptingProcessStep {
	private const MAX_TRANSACTION_STEPS = 900;

	/**
	 * @param HookContainer $hookContainer
	 * @param InstanceManager $instanceManager
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 * @param string $instanceId
	 */
	public function __construct(
		private readonly HookContainer $hookContainer,
		private readonly InstanceManager $instanceManager,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language, string $loggerName,
		private readonly string $instanceId
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager, $this->instanceId );

		try {
			$backend = $this->storage->getBackend(
				$this->instanceManager->getFarmConfig()->get( 'instanceStorageBackend' )
			);
			$instanceTransaction = new InstanceTransaction( $backend );
			$transaction = $this->storage->newTempTransaction();
			$files = $backend->getFileList( [
				'dir' => $instanceTransaction->makeInstancePath( $instance->getPath() ),
				'topOnly' => false
			] );

			$exportedFilesCount = 0;
			$tempFilesToStore = [];
			$transactionSteps = 0;
			foreach ( $files as $relativePath ) {
				$sourcePath = $instanceTransaction->makeInstancePath( $instance->getPath(), $relativePath );
				if ( $backend->getName() === 'AmazonS3' ) {
					// In s3, only images are stored in instance dir, and without `images/` prefix
					$relativePath = 'images/' . $relativePath;
				}
				if ( !$this->isExportableImagePath( $relativePath ) ) {
					continue;
				}

				$targetFilename = basename( $relativePath );
				$targetDir = $this->getExportDirectory() . '/' . trim( dirname( $relativePath ), '/' );

				$this->storage->getTempFilePath( $targetFilename, $targetDir, true );

				$tempFile = $backend->getLocalCopy( [ 'src' => $sourcePath ] );
				if ( !$tempFile ) {
					$this->logger->warning( 'ExportImages: failed to read source file: {source}', [
						'instanceId' => $this->getInstanceId(),
						'source' => $relativePath,
					] );
					continue;
				}
				$tempFilesToStore[] = $tempFile;

				$transaction->store(
					$tempFile->getPath(),
					$targetFilename,
					$targetDir
				);
				$this->addFileToManifest( $targetFilename, $targetDir );
				$exportedFilesCount++;
				$transactionSteps++;

				if ( $transactionSteps >= self::MAX_TRANSACTION_STEPS ) {
					$status = $transaction->commit();
					unset( $tempFilesToStore );
					$this->validateStatus( $status );
					$transaction = $this->storage->newTempTransaction();
					$tempFilesToStore = [];
					$transactionSteps = 0;
				}
			}

			if ( $transactionSteps > 0 ) {
				$status = $transaction->commit();
				unset( $tempFilesToStore );
				$this->validateStatus( $status );
			}
		} catch ( Throwable $e ) {
			$this->logger->error( 'ExportImages failed: unable to export image files', [
				'instanceId' => $this->getInstanceId(),
				'instancePath' => $instance->getPath(),
				'error' => $e->getMessage(),
			] );
			throw new RuntimeException( 'Could not export images: ' . $e->getMessage(), 0, $e );
		}

		$this->addManifestInfo( 'imagesExportDirectory', $this->getExportDirectory() . '/images' );
		$this->addToData( 'imagesExportedFiles', $exportedFilesCount );
	}

	/**
	 * @param string $relativePath
	 * @return bool
	 */
	private function isExportableImagePath( string $relativePath ): bool {
		if ( !str_starts_with( $relativePath, 'images/' ) ) {
			return false;
		}

		$pathInImages = substr( $relativePath, strlen( 'images/' ) );
		$shouldExport = !str_contains( $pathInImages, '.htaccess' ) &&
			!str_contains( $pathInImages, 'index.html' ) &&
			!str_starts_with( $pathInImages, 'thumb/' ) &&
			!str_starts_with( $pathInImages, 'temp/' ) &&
			!str_starts_with( $pathInImages, 'cache/' );

		$this->hookContainer->run(
			'BlueSpiceWikiFarmExportIsExportableImagePath', [ $relativePath, $pathInImages, &$shouldExport ]
		);
		return $shouldExport;
	}
}
