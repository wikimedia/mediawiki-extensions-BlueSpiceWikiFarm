<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\Import\PackageValidator;
use BlueSpice\WikiFarm\InstanceManager;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageTransaction;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;

class Cleanup extends ImportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param PackageValidator $packageValidator
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 * @param string $packageName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		private readonly PackageValidator $packageValidator,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName, private readonly string $packageName
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	protected function runStep(): void {
		$packageName = $this->data['packageName'] ?? $this->packageName;
		if ( !is_string( $packageName ) || $packageName === '' ) {
			$this->logger->warning( 'Skipping import package cleanup: package name is missing' );
			return;
		}

		$instanceStorageBackend = $this->storage->getBackend(
			$this->instanceManager->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		$status = ( new StorageTransaction( $instanceStorageBackend ) )
			->setContainer( 'export-import' )
			->delete( $packageName, '_import' )
			->commit();
		if ( !$status->isOK() ) {
			$this->logger->warning( 'Failed to delete imported package file during cleanup', [
				'packageName' => $packageName,
				'errors' => $status->getErrors(),
			] );
		}

		$this->packageValidator->cleanupTempFiles( $packageName );
		$this->addToData( 'cleanup', true );
	}
}
