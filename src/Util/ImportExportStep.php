<?php

namespace BlueSpice\WikiFarm\Util;

use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\StatusFormatter;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use Psr\Log\LoggerInterface;
use RuntimeException;
use StatusValue;

abstract class ImportExportStep {

	/** @var array */
	protected array $data;

	/** @var StatusFormatter */
	protected readonly StatusFormatter $statusFormatter;

	/** @var LoggerInterface */
	protected readonly LoggerInterface $logger;

	/**
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $contentLanguage
	 * @param string $loggerName
	 */
	public function __construct(
		protected readonly StorageHandler $storage,
		protected readonly FormatterFactory $formatterFactory,
		protected readonly Language $contentLanguage,
		protected readonly string $loggerName
	) {
		$this->statusFormatter = $this->formatterFactory->getStatusFormatter(
			new ProcessMessageLocalizer( $this->contentLanguage )
		);
		$this->logger = LoggerFactory::getInstance( $this->loggerName );
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		$this->setData( $data );

		$this->runStep();

		return $this->getOutputData();
	}

	/**
	 * @return void
	 */
	abstract protected function runStep(): void;

	/**
	 * @param array $data
	 * @return void
	 */
	protected function setData( array $data ): void {
		$this->data = $data;
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function addToData( string $key, mixed $value ): void {
		$this->data[$key] = $value;
	}

	/**
	 * @return array
	 */
	protected function getOutputData(): array {
		return $this->data;
	}

	protected function validateStatus( StatusValue $status ): void {
		if ( !$status->isOK() ) {
			$errorMessage = $this->statusFormatter->getMessage( $status );
			throw new RuntimeException( $errorMessage->text() );
		}
	}
}
