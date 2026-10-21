<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\Import\PackageValidator;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;

class ValidatePackage extends ImportStep implements InterruptingProcessStep {

	/**
	 * @param PackageValidator $packageValidator
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 * @param string $packageName
	 */
	public function __construct(
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
		$status = $this->packageValidator->validate( $this->packageName );
		$this->validateStatus( $status );
		$validationData = $status->getValue();
		if ( !is_array( $validationData ) ) {
			throw new RuntimeException( 'Package validation did not return import data' );
		}
		if ( !isset( $validationData['importPackagePath'] ) || !is_string( $validationData['importPackagePath'] ) ) {
			throw new RuntimeException( 'Package validation did not return importPackagePath' );
		}
		if ( !isset( $validationData['manifest'] ) || !is_array( $validationData['manifest'] ) ) {
			throw new RuntimeException( 'Package validation did not return manifest data' );
		}

		$this->addToData( 'packageName', $this->packageName );
		$this->addToData( 'importPackagePath', $validationData['importPackagePath'] );
		$this->addToData( 'manifest', $validationData['manifest'] );
	}
}
