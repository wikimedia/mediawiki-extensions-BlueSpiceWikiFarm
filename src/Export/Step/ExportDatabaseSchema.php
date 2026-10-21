<?php

namespace BlueSpice\WikiFarm\Export\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Util\DoctrineDatabaseConnection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;
use InvalidArgumentException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Throwable;
use Wikimedia\Rdbms\IDatabase;

class ExportDatabaseSchema extends ExportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param DoctrineDatabaseConnection $doctrineDatabaseConnection
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 * @param string $instanceId
	 */
	public function __construct(
		protected readonly InstanceManager $instanceManager,
		protected readonly DoctrineDatabaseConnection $doctrineDatabaseConnection,
		StorageHandler $storage,
		FormatterFactory $formatterFactory,
		Language $language,
		string $loggerName,
		protected readonly string $instanceId
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager, $this->instanceId );
		$db = $this->getInstanceDatabase( $instance );

		try {
			$schemaData = $this->buildSchemaExportData( $db );
			$schemaJson = json_encode( $schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR );
		} catch ( Throwable $e ) {
			$this->logger->error( 'ExportDatabaseSchema failed: unable to inspect schema', [
				'instanceId' => $this->getInstanceId(),
				'error' => $e->getMessage(),
			] );
			throw new RuntimeException( $e->getMessage(), 0, $e );
		}

		$status = $this->storage->newTempTransaction()
			->create(
				filename: 'database-schema.json',
				content: $schemaJson,
				path: $this->getExportDirectory()
			)->commit();
		$this->validateStatus( $status );
		$this->addFileToManifest( 'database-schema.json' );
		$this->addManifestInfo( 'databaseSchemaFile', 'database-schema.json' );
	}

	/**
	 * @param InstanceEntity $instance
	 * @return IDatabase
	 */
	protected function getInstanceDatabase( InstanceEntity $instance ): IDatabase {
		$db = $this->instanceManager->getDatabaseConnectionForInstance( $instance );
		if ( !$db ) {
			$this->logger->error(
				"ExportDatabaseSchema failed: InstanceManager cannot retrieve database connection for the instance", [
					'instanceId' => $this->getInstanceId(),
				]
			);
			throw new InvalidArgumentException( Message::newFromKey( 'wikifarm-export-error-instance-no-db' )->text() );
		}
		return $db;
	}

	/**
	 * @param IDatabase $db
	 * @return array
	 * @throws DbalException
	 */
	private function buildSchemaExportData( IDatabase $db ): array {
		$connection = $this->doctrineDatabaseConnection->getDoctrineConnectionForMediawikiConnection( $db );

		try {
			$schemaManager = $connection->createSchemaManager();
			$platform = $connection->getDatabasePlatform();
			if ( $db->getType() === 'mysql' ) {
				$platform->registerDoctrineTypeMapping( 'enum', 'string' );
				$platform->registerDoctrineTypeMapping( 'set', 'string' );
			}
			$prefix = $db->tablePrefix();
			$tableNames = $schemaManager->listTableNames();
			$tableSchemas = [];

			foreach ( $tableNames as $tableName ) {
				if ( !$this->shouldExportTable( $tableName, $prefix ) ) {
					continue;
				}
				$table = $schemaManager->introspectTable( $tableName );
				$tableSchemas[$tableName] = $this->serializeTableDefinition( $table );
			}
		} finally {
			$connection->close();
		}

		return [
			'driver' => $db->getType(),
			'database' => $db->getDBname(),
			'tablePrefix' => $prefix,
			'exportedAt' => gmdate( 'c' ),
			'tables' => $tableSchemas,
		];
	}

	/**
	 * @param string $tableName
	 * @param string $prefix
	 * @return bool
	 */
	protected function shouldExportTable( string $tableName, string $prefix ): bool {
		return $prefix === '' || str_starts_with( $tableName, $prefix );
	}

	/**
	 * @param Table $table
	 * @return array
	 */
	private function serializeTableDefinition( Table $table ): array {
		$columns = [];
		foreach ( $table->getColumns() as $column ) {
			$columns[$column->getName()] = $this->serializeColumnDefinition( $column );
		}

		$indexes = [];
		foreach ( $table->getIndexes() as $index ) {
			$indexes[$index->getName()] = $this->serializeIndexDefinition( $index );
		}

		$foreignKeys = [];
		foreach ( $table->getForeignKeys() as $foreignKey ) {
			$foreignKeys[] = $this->serializeForeignKeyDefinition( $foreignKey );
		}

		return [
			'name' => $table->getName(),
			'options' => $table->getOptions(),
			'columns' => $columns,
			'primaryKey' => $table->getPrimaryKey()?->getColumns() ?? [],
			'indexes' => $indexes,
			'foreignKeys' => $foreignKeys,
		];
	}

	/**
	 * @param Column $column
	 * @return array
	 */
	private function serializeColumnDefinition( Column $column ): array {
		$platformOptions = $column->getPlatformOptions();
		$enumValues = $platformOptions['values'] ?? [];

		return [
			'type' => $column->getType()->getName(),
			'length' => $column->getLength(),
			'precision' => $column->getPrecision(),
			'scale' => $column->getScale(),
			'fixed' => $column->getFixed(),
			'unsigned' => $column->getUnsigned(),
			'notNull' => $column->getNotnull(),
			'default' => $column->getDefault(),
			'autoIncrement' => $column->getAutoincrement(),
			'comment' => $column->getComment(),
			'columnDefinition' => $column->getColumnDefinition(),
			'enumValues' => is_array( $enumValues ) ? $enumValues : [],
			'platformOptions' => $platformOptions,
		];
	}

	/**
	 * @param Index $index
	 * @return array
	 */
	private function serializeIndexDefinition( Index $index ): array {
		return [
			'columns' => $index->getColumns(),
			'unique' => $index->isUnique(),
			'primary' => $index->isPrimary(),
			'flags' => $index->getFlags(),
			'options' => $index->getOptions(),
		];
	}

	/**
	 * @param ForeignKeyConstraint $foreignKey
	 * @return array
	 */
	private function serializeForeignKeyDefinition( ForeignKeyConstraint $foreignKey ): array {
		return [
			'name' => $foreignKey->getName(),
			'localColumns' => $foreignKey->getLocalColumns(),
			'foreignTable' => $foreignKey->getForeignTableName(),
			'foreignColumns' => $foreignKey->getForeignColumns(),
			'onUpdate' => $foreignKey->onUpdate(),
			'onDelete' => $foreignKey->onDelete(),
			'options' => $foreignKey->getOptions(),
		];
	}

}
