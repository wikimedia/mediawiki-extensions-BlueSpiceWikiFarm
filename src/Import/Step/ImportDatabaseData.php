<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceManager;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Throwable;
use XMLReader;

class ImportDatabaseData extends ImportStep implements InterruptingProcessStep {

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
		$dataFilename = $this->getDatabaseDataFile();
		if ( !$dataFilename ) {
			throw new RuntimeException( 'Imported package does not contain database data file info' );
		}

		$instance = $this->getInstance( $this->instanceManager );
		$db = $this->instanceManager->getDatabaseConnectionForInstance( $instance );
		if ( !$db ) {
			$this->logger->error( 'ImportDatabaseData failed: cannot connect to instance database', [
				'instanceId' => $this->getInstanceId(),
				'dbname' => $instance->getDbName(),
				'prefix' => $instance->getDbPrefix(),
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-import-error-instance-no-db' )->text() );
		}

		$dataFile = $this->resolveImportedDataFile( $dataFilename );
		$reader = new XMLReader();
		if ( !$reader->open( $dataFile->getPath(), null, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING ) ) {
			$this->logger->error( 'ImportDatabaseData failed to open XML database data file', [
				'instanceId' => $this->getInstanceId(),
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-import-error-cannot-open-xml' )->text() );
		}

		$currentTable = null;
		$currentRow = [];
		$insertedRows = 0;

		try {
			if ( $db->getType() === 'mysql' ) {
				$db->query( 'SET FOREIGN_KEY_CHECKS=0', __METHOD__ );
			}

			while ( $reader->read() ) {
				if ( $reader->nodeType === XMLReader::ELEMENT ) {
					if ( $reader->name === 'table' ) {
						$currentTable = $reader->getAttribute( 'name' );
					} elseif ( $reader->name === 'row' ) {
						$currentRow = [];
					} elseif ( $reader->name === 'column' ) {
						$columnName = $reader->getAttribute( 'name' );
						if ( !$columnName ) {
							continue;
						}
						$currentRow[$columnName] = $this->decodeColumnValue(
							$reader->readString(),
							$reader->getAttribute( 'null' ),
							$reader->getAttribute( 'type' ),
							$reader->getAttribute( 'encoding' )
						);
					}
					continue;
				}

				if ( $reader->nodeType === XMLReader::END_ELEMENT && $reader->name === 'row' ) {
					if ( !$currentTable || $currentRow === [] ) {
						continue;
					}
					$db->newInsertQueryBuilder()
						->insert( $currentTable )
						->row( $currentRow )
						->caller( __METHOD__ )
						->execute();
					$insertedRows++;
				}
			}
		} catch ( Throwable $e ) {
			$this->logger->error( 'ImportDatabaseData failed while importing rows', [
				'instanceId' => $this->getInstanceId(),
				'table' => $currentTable,
				'insertedRows' => $insertedRows,
				'error' => $e->getMessage(),
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-import-error-importing-rows' )->text() );
		} finally {
			$reader->close();
			if ( $db->getType() === 'mysql' ) {
				$db->query( 'SET FOREIGN_KEY_CHECKS=1', __METHOD__ );
			}
		}

		$this->addToData( 'importedRows', $insertedRows );
	}

	/**
	 * @param string $dataFilename
	 * @return \MWStake\MediaWiki\Component\FileStorageUtilities\StoredFile
	 */
	private function resolveImportedDataFile( string $dataFilename ) {
		$importPath = trim( (string)$this->getImportPackagePath(), '/' );
		$file = $this->storage->getTempFile( $dataFilename, $importPath );
		if ( !$file ) {
			$this->logger->error( 'ImportDatabaseData failed to resolve imported data file', [
				'instanceId' => $this->getInstanceId(),
				'filename' => $dataFilename,
				'importPath' => $importPath,
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-import-error-cannot-resolve-data-file' )->text() );
		}
		return $file;
	}

	/**
	 * @param string $rawValue
	 * @param string|null $nullAttr
	 * @param string|null $typeAttr
	 * @param string|null $encodingAttr
	 * @return mixed
	 */
	private function decodeColumnValue(
		string $rawValue,
		?string $nullAttr,
		?string $typeAttr,
		?string $encodingAttr
	): mixed {
		if ( $nullAttr === 'true' ) {
			return null;
		}

		$value = $rawValue;
		if ( $encodingAttr === 'base64' ) {
			$decoded = base64_decode( $rawValue, true );
			$value = $decoded !== false ? $decoded : '';
		}

		return match ( $typeAttr ) {
			'bool' => $value === '1' || strtolower( (string)$value ) === 'true',
			'int' => (int)$value,
			'float' => (float)$value,
			default => $value,
		};
	}
}
