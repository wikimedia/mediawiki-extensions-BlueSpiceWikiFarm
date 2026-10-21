<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceManager;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHelper;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageTransaction;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;

class ImportImages extends ImportStep implements InterruptingProcessStep {
	private const MAX_TRANSACTION_STEPS = 900;

	/**
	 * @param InstanceManager $instanceManager
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager );
		$importPath = trim( (string)$this->getImportPackagePath(), '/' );

		$instanceStorageBackend = $this->storage->getBackend(
			$this->instanceManager->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		$storageHelper = new StorageHelper( $instanceStorageBackend );
		if ( $instanceStorageBackend->getName() === 'AmazonS3' ) {
			// Due to configuration, on S3 bucket, images are not stored in `images/` subdir
			$targetPath = trim( $instance->getPath(), '/' );
		} else {
			$targetPath = trim( $instance->getPath(), '/' ) . '/images';
		}

		$targetDir = $storageHelper->compileZonePath( $targetPath, '', 'instances-public' );
		$prepareStatus = $instanceStorageBackend->prepare( [ 'dir' => $targetDir ] );
		$this->validateStatus( $prepareStatus );

		$manifestFiles = $this->data['manifest']['files'] ?? [];
		$transaction = ( new StorageTransaction( $instanceStorageBackend ) )
			->setContainer( 'instances-public' );
		$importedImages = 0;
		$tempSourceFiles = [];
		$transactionSteps = 0;

		foreach ( $manifestFiles as $manifestFile ) {
			$file = $manifestFile['file'] ?? null;
			$path = trim( (string)( $manifestFile['path'] ?? '' ), '/' );
			if ( !is_string( $file ) || $file === '' ) {
				continue;
			}
			if ( $path !== 'images' && !str_starts_with( $path, 'images/' ) ) {
				continue;
			}

			$sourceFile = $this->storage->getTempFile( $file, $importPath . '/' . $path );
			if ( !$sourceFile ) {
				$this->logger->error( "Imported image file not found: {$importPath}/{$path}/{$file}" );
				throw new RuntimeException( Message::newFromKey( 'wikifarm-import-error-cannot-resolve-image-file' )->text() );
			}
			$tempSourceFiles[] = $sourceFile;
			$imagesSubpath = trim( substr( $path, strlen( 'images' ) ), '/' );
			$imageTargetPath = $targetPath . ( $imagesSubpath !== '' ? '/' . $imagesSubpath : '' );
			$imageTargetDir = $storageHelper->compileZonePath( $imageTargetPath, '', 'instances-public' );
			$prepareImageDirStatus = $instanceStorageBackend->prepare( [ 'dir' => $imageTargetDir ] );
			$this->validateStatus( $prepareImageDirStatus );

			$transaction->store(
				$sourceFile->getPath(),
				$file,
				$imageTargetPath
			);
			$importedImages++;
			$transactionSteps++;

			if ( $transactionSteps >= self::MAX_TRANSACTION_STEPS ) {
				$status = $transaction->commit();
				unset( $tempSourceFiles );
				$this->validateStatus( $status );
				$transaction = ( new StorageTransaction( $instanceStorageBackend ) )
					->setContainer( 'instances-public' );
				$tempSourceFiles = [];
				$transactionSteps = 0;
			}
		}

		if ( $transactionSteps > 0 ) {
			$status = $transaction->commit();
			unset( $tempSourceFiles );
			$this->validateStatus( $status );
		}
		$this->addToData( 'importedImages', $importedImages );
	}
}
