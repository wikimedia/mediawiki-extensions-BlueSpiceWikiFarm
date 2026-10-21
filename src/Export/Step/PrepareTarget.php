<?php

namespace BlueSpice\WikiFarm\Export\Step;

use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;

class PrepareTarget extends ExportStep implements InterruptingProcessStep {

	/**
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 * @param string $instanceId
	 */
	public function __construct(
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName, private readonly string $instanceId
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 */
	protected function runStep(): void {
		$exportId = md5( $this->instanceId . uniqid( rand(), true ) );
		$dirPath = "WikiExport/$exportId";

		$this->logger->info( "Preparing to export instance {instanceId}, id: {exportId}", [
			'instanceId' => $this->instanceId,
			'exportId' => $exportId,
		] );

		$status = $this->storage->newTempTransaction()
			->create(
				filename: 'export.id',
				content: $exportId,
				path: $dirPath
			)->commit();

		$this->validateStatus( $status );

		$file = $this->storage->getTempFile( filename: 'export.id', path: $dirPath );
		if ( !$file || file_get_contents( $file->getPath() ) !== $exportId ) {
			$errorMessage = Message::newFromKey( 'wikifarm-export-error-temp-dir-not-writable' );
			$this->logger->error( "PrepareTarget failed: {$errorMessage->text()}" );
			throw new RuntimeException( $errorMessage->text() );
		}

		$this->setData( [
			'instanceId' => $this->instanceId,
			'exportId' => $exportId,
			'dirPath' => $dirPath,
		] );
	}
}
