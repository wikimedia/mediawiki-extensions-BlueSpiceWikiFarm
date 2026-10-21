<?php

namespace BlueSpice\WikiFarm\Export\Step;

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Util\DoctrineDatabaseConnection;
use Doctrine\DBAL\Exception;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Throwable;
use XMLWriter;

class ExportDatabaseData extends ExportDatabaseSchema implements InterruptingProcessStep {

	/**
	 * @param HookContainer $hookContainer
	 * @param InstanceManager $instanceManager
	 * @param DoctrineDatabaseConnection $doctrineDatabaseConnection
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 * @param string $instanceId
	 */
	public function __construct(
		private readonly HookContainer $hookContainer,
		InstanceManager $instanceManager, DoctrineDatabaseConnection $doctrineDatabaseConnection,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName, string $instanceId
	) {
		parent::__construct(
			$instanceManager, $doctrineDatabaseConnection, $storage,
			$formatterFactory, $language, $loggerName, $instanceId
		);
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager, $this->instanceId );
		$db = $this->getInstanceDatabase( $instance );
		$connection = $this->doctrineDatabaseConnection->getDoctrineConnectionForMediawikiConnection( $db );

		$tmpFilePath = $this->storage->getTempFilePath( 'database-data.xml' );
		$writer = new XMLWriter();

		if ( !$writer->openUri( $tmpFilePath ) ) {
			$this->logger->error( 'ExportDatabaseData failed: unable to open temp file', [
				'instanceId' => $this->getInstanceId(),
				'file' => $tmpFilePath,
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-export-error-cannot-open-xml' )->text() );
		}

		$skipTables = [
			'objectcache',
			'l10n_cache',
			'querycache',
			'querycachetwo',
			'querycache_info',
			'parser_cache',
			'pagelinks',
			'templatelinks',
			'imagelinks',
			'categorylinks',
			'externallinks',
			'iwlinks',
			'langlinks',
			'redirect',
			'page_props',
			'searchindex'
		];
		$this->hookContainer->run( 'BlueSpiceWikiFarmExportDatabaseDataSkipTables', [ &$skipTables ] );
		$this->logger->debug( 'ExportDatabaseData: skipping tables: ' . implode( ', ', $skipTables ) );

		try {
			$schemaManager = $connection->createSchemaManager();
			$platform = $connection->getDatabasePlatform();
			if ( $db->getType() === 'mysql' ) {
				$platform->registerDoctrineTypeMapping( 'enum', 'string' );
				$platform->registerDoctrineTypeMapping( 'set', 'string' );
			}

			$tablePrefix = $db->tablePrefix();
			$tableNames = $schemaManager->listTableNames();

			$writer->startDocument( '1.0', 'UTF-8' );
			$writer->setIndent( true );
			$writer->startElement( 'databaseData' );
			$writer->writeAttribute( 'driver', $db->getType() );
			$writer->writeAttribute( 'database', $db->getDBname() );
			$writer->writeAttribute( 'tablePrefix', $tablePrefix );
			$writer->writeAttribute( 'exportedAt', gmdate( 'c' ) );
			$writer->writeAttribute( 'skippedTables', implode( ',', $skipTables ) );

			foreach ( $tableNames as $tableName ) {
				if (
					!$this->shouldExportTable( $tableName, $tablePrefix ) ||
					$this->isSkippedTable( $tableName, $tablePrefix, $skipTables )
				) {
					continue;
				}

				$table = $schemaManager->introspectTable( $tableName );
				$primaryKeyColumns = $table->getPrimaryKey()?->getColumns() ?? [];
				$queryBuilder = $connection->createQueryBuilder()
					->select( '*' )
					->from( $connection->quoteIdentifier( $tableName ) );

				foreach ( $primaryKeyColumns as $primaryKeyColumn ) {
					$queryBuilder->addOrderBy( $connection->quoteIdentifier( $primaryKeyColumn ), 'ASC' );
				}

				$writer->startElement( 'table' );
				$writer->writeAttribute( 'name', $tableName );

				$result = $queryBuilder->executeQuery();
				while ( true ) {
					$row = $result->fetchAssociative();
					if ( $row === false ) {
						break;
					}
					$writer->startElement( 'row' );
					foreach ( $row as $columnName => $value ) {
						$writer->startElement( 'column' );
						$writer->writeAttribute( 'name', (string)$columnName );
						$this->writeColumnValue( $writer, $value );
						$writer->endElement();
					}

					$writer->endElement();
				}

				$writer->endElement();
			}

			$writer->endElement();
			$writer->endDocument();
		} catch ( Throwable $e ) {
			$this->logger->error( 'ExportDatabaseData failed: unable to export data', [
				'instanceId' => $this->getInstanceId(),
				'error' => $e->getMessage(),
				'backtrace' => $e->getTraceAsString(),
			] );
			throw new RuntimeException( $e->getMessage(), 0, $e );
		} finally {
			$writer->flush();
			$connection->close();
		}

		$xmlData = file_get_contents( $tmpFilePath );
		if ( $xmlData === false ) {
			$this->logger->error( 'ExportDatabaseData failed: unable to read temp file', [
				'instanceId' => $this->getInstanceId(),
				'file' => $tmpFilePath,
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-export-error-cannot-open-xml' )->text() );
		}

		$status = $this->storage->newTempTransaction()
			->create(
				filename: 'database-data.xml',
				content: $xmlData,
				path: $this->getExportDirectory()
			)->commit();

		// Cleanup
		$this->storage->newTempTransaction()->delete(
			filename: 'database-data.xml', path: ''
		)->commit();

		$this->validateStatus( $status );
		$this->addFileToManifest( 'database-data.xml' );
		$this->addManifestInfo( 'databaseDataFile', 'database-data.xml' );
	}

	/**
	 * @param string $tableName
	 * @param string $prefix
	 * @param array $skipTables
	 * @return bool
	 */
	protected function isSkippedTable( string $tableName, string $prefix, array $skipTables ): bool {
		$shortName = $prefix !== '' && str_starts_with( $tableName, $prefix )
			? substr( $tableName, strlen( $prefix ) )
			: $tableName;

		return in_array( $tableName, $skipTables, true ) ||
			in_array( $shortName, $skipTables, true );
	}

	/**
	 * @param XMLWriter $writer
	 * @param mixed $value
	 * @return void
	 */
	private function writeColumnValue( XMLWriter $writer, mixed $value ): void {
		if ( $value === null ) {
			$writer->writeAttribute( 'null', 'true' );
			return;
		}

		if ( gettype( $value ) === 'resource' ) {
			$streamContent = stream_get_contents( $value );
			$writer->writeAttribute( 'encoding', 'base64' );
			$writer->text( base64_encode( $streamContent !== false ? $streamContent : '' ) );
			return;
		}

		if ( is_bool( $value ) ) {
			$writer->writeAttribute( 'type', 'bool' );
			$writer->text( $value ? '1' : '0' );
			return;
		}

		if ( is_int( $value ) ) {
			$writer->writeAttribute( 'type', 'int' );
			$writer->text( (string)$value );
			return;
		}

		if ( is_float( $value ) ) {
			$writer->writeAttribute( 'type', 'float' );
			$writer->text( (string)$value );
			return;
		}

		$stringValue = (string)$value;
		if ( !preg_match( '//u', $stringValue ) ) {
			$writer->writeAttribute( 'encoding', 'base64' );
			$writer->text( base64_encode( $stringValue ) );
			return;
		}

		$writer->text( $stringValue );
	}

}
