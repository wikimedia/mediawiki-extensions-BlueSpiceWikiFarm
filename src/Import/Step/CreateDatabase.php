<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use BlueSpice\WikiFarm\Util\DoctrineDatabaseConnection;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use InvalidArgumentException;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Throwable;
use Wikimedia\Rdbms\IDatabase;

class CreateDatabase extends ImportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param ManagementDatabaseFactory $managementDatabaseFactory
	 * @param DoctrineDatabaseConnection $doctrineDatabaseConnection
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		private readonly ManagementDatabaseFactory $managementDatabaseFactory,
		private readonly DoctrineDatabaseConnection $doctrineDatabaseConnection,
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
		$databaseSchemaFilename = $this->getDatabaseSchemaFile();
		if ( !$databaseSchemaFilename ) {
			throw new InvalidArgumentException( 'Imported package does not contain database schema file info' );
		}
		$instance = $this->getInstance( $this->instanceManager );

		$this->createDatabaseIfMissing( $instance->getDbName() );
		$instanceDb = $this->instanceManager->getDatabaseConnectionForInstance( $instance );
		if ( !$instanceDb ) {
			$this->logger->error( 'CreateDatabase failed: cannot connect to instance database', [
				'instanceId' => $this->getInstanceId(),
			] );
			throw new RuntimeException( Message::newFromKey( 'wikifarm-export-error-instance-no-db' )->text() );
		}

		$schemaFile = $this->resolveImportedSchemaFile( $databaseSchemaFilename );
		$schemaRaw = file_get_contents( $schemaFile->getPath() );
		if ( $schemaRaw === false ) {
			$this->logger->error( 'CreateDatabase failed to read imported database schema file', [
				'instanceId' => $this->getInstanceId()
			] );
			throw new RuntimeException(
				Message::newFromKey( 'wikifarm-import-error-instance-cannot-read-schema' )->text()
			);
		}
		$schemaData = json_decode( $schemaRaw, true, 512, JSON_THROW_ON_ERROR );
		$this->validateSchemaCompatibility( $schemaData, $instanceDb );

		$doctrineConnection = $this->doctrineDatabaseConnection->getDoctrineConnectionForMediawikiConnection( $instanceDb );
		$platform = $doctrineConnection->getDatabasePlatform();
		try {
			if ( $instanceDb->getType() === 'mysql' ) {
				$instanceDb->query( 'SET FOREIGN_KEY_CHECKS=0', __METHOD__ );
			}

			foreach ( $schemaData['tables'] as $tableName => $tableDefinition ) {
				if ( !is_array( $tableDefinition ) ) {
					throw new RuntimeException( "Invalid schema table definition for table: {$tableName}" );
				}
				$table = $this->buildTableDefinition( (string)$tableName, $tableDefinition );
				$statements = $platform->getCreateTableSQL( $table );
				foreach ( $statements as $sql ) {
					$instanceDb->query( $sql, __METHOD__ );
				}
			}
		} catch ( Throwable $e ) {
			$this->logger->error( 'CreateDatabase failed while creating database schema', [
				'instanceId' => $this->getInstanceId(),
				'error' => $e->getMessage(),
			] );
			throw new RuntimeException( $e->getMessage(), 0, $e );
		} finally {
			if ( $instanceDb->getType() === 'mysql' ) {
				$instanceDb->query( 'SET FOREIGN_KEY_CHECKS=1', __METHOD__ );
			}
			$doctrineConnection->close();
			$this->addToData( 'createDatabase', true );
		}
	}

	/**
	 * @param string $schemaFilename
	 * @return \MWStake\MediaWiki\Component\FileStorageUtilities\StoredFile
	 */
	private function resolveImportedSchemaFile( string $schemaFilename ) {
		$importPath = trim( (string)$this->getImportPackagePath(), '/' );
		$file = $this->storage->getTempFile( $schemaFilename, $importPath );
		if ( !$file ) {
			throw new RuntimeException( "Imported schema file not found: {$importPath}/{$schemaFilename}" );
		}
		return $file;
	}

	/**
	 * @param array $schemaData
	 * @param IDatabase $instanceDb
	 * @return void
	 */
	private function validateSchemaCompatibility( array $schemaData, IDatabase $instanceDb ): void {
		$schemaDriver = $this->normalizeDbType( (string)( $schemaData['driver'] ?? '' ) );
		$instanceDriver = $this->normalizeDbType( $instanceDb->getType() );
		if ( $schemaDriver === '' || $schemaDriver !== $instanceDriver ) {
			throw new RuntimeException(
				"Schema DB type mismatch: package={$schemaDriver}, target={$instanceDriver}"
			);
		}
		if ( !isset( $schemaData['tables'] ) || !is_array( $schemaData['tables'] ) ) {
			throw new RuntimeException( 'Invalid schema format: tables definition missing' );
		}
	}

	/**
	 * @param string $dbType
	 * @return string
	 */
	private function normalizeDbType( string $dbType ): string {
		return match ( strtolower( $dbType ) ) {
			'postgresql' => 'postgres',
			default => strtolower( $dbType ),
		};
	}

	/**
	 * @param string $tableName
	 * @param array $tableDefinition
	 * @return Table
	 */
	private function buildTableDefinition( string $tableName, array $tableDefinition ): Table {
		$table = new Table( $tableName, [], [], [], [], $tableDefinition['options'] ?? [] );

		$columns = $tableDefinition['columns'] ?? [];
		foreach ( $columns as $columnName => $columnDefinition ) {
			if ( !is_array( $columnDefinition ) ) {
				throw new RuntimeException( "Invalid column definition for {$tableName}.{$columnName}" );
			}
			$typeName = (string)( $columnDefinition['type'] ?? '' );
			if ( $typeName === '' ) {
				throw new RuntimeException( "Missing column type for {$tableName}.{$columnName}" );
			}
			if ( !Type::hasType( $typeName ) ) {
				$typeName = 'string';
			}

			$options = [];
			$optionMap = [
				'length' => 'length',
				'precision' => 'precision',
				'scale' => 'scale',
				'fixed' => 'fixed',
				'unsigned' => 'unsigned',
				'notNull' => 'notnull',
				'default' => 'default',
				'autoIncrement' => 'autoincrement',
				'comment' => 'comment',
				'columnDefinition' => 'columnDefinition',
			];
			foreach ( $optionMap as $exportKey => $doctrineKey ) {
				if ( array_key_exists( $exportKey, $columnDefinition ) && $columnDefinition[$exportKey] !== null ) {
					$options[$doctrineKey] = $columnDefinition[$exportKey];
				}
			}
			if ( array_key_exists( 'default', $options ) ) {
				$options['default'] = $this->normalizeColumnDefaultForType( $typeName, $options['default'] );
			}
			if ( isset( $columnDefinition['platformOptions'] ) && is_array( $columnDefinition['platformOptions'] ) ) {
				$options['platformOptions'] = $columnDefinition['platformOptions'];
			}

			$table->addColumn( (string)$columnName, $typeName, $options );
		}

		$primaryKey = $tableDefinition['primaryKey'] ?? [];
		if ( is_array( $primaryKey ) && $primaryKey !== [] ) {
			$table->setPrimaryKey( $primaryKey );
		}

		$indexes = $tableDefinition['indexes'] ?? [];
		foreach ( $indexes as $indexName => $indexDefinition ) {
			if ( !is_array( $indexDefinition ) ) {
				continue;
			}
			$indexColumns = $indexDefinition['columns'] ?? [];
			if ( !is_array( $indexColumns ) || $indexColumns === [] ) {
				continue;
			}
			$isPrimary = (bool)( $indexDefinition['primary'] ?? false );
			if ( $isPrimary ) {
				continue;
			}
			$flags = isset( $indexDefinition['flags'] ) && is_array( $indexDefinition['flags'] ) ?
				$indexDefinition['flags'] : [];
			$options = isset( $indexDefinition['options'] ) && is_array( $indexDefinition['options'] ) ?
				$indexDefinition['options'] : [];
			$isUnique = (bool)( $indexDefinition['unique'] ?? false );
			if ( $isUnique ) {
				$table->addUniqueIndex( $indexColumns, (string)$indexName, $options );
				continue;
			}
			$table->addIndex( $indexColumns, (string)$indexName, $flags, $options );
		}

		$foreignKeys = $tableDefinition['foreignKeys'] ?? [];
		foreach ( $foreignKeys as $foreignKey ) {
			if ( !is_array( $foreignKey ) ) {
				continue;
			}
			$localColumns = $foreignKey['localColumns'] ?? [];
			$foreignColumns = $foreignKey['foreignColumns'] ?? [];
			$foreignTable = $foreignKey['foreignTable'] ?? null;
			if ( !is_string( $foreignTable ) || !is_array( $localColumns ) || !is_array( $foreignColumns ) ) {
				continue;
			}
			$options = isset( $foreignKey['options'] ) && is_array( $foreignKey['options'] ) ? $foreignKey['options'] : [];
			$onUpdate = $foreignKey['onUpdate'] ?? null;
			$onDelete = $foreignKey['onDelete'] ?? null;
			if ( is_string( $onUpdate ) && $onUpdate !== '' ) {
				$options['onUpdate'] = $onUpdate;
			}
			if ( is_string( $onDelete ) && $onDelete !== '' ) {
				$options['onDelete'] = $onDelete;
			}
			$table->addForeignKeyConstraint(
				$foreignTable,
				$localColumns,
				$foreignColumns,
				$options,
				is_string( $foreignKey['name'] ?? null ) ? $foreignKey['name'] : null
			);
		}

		return $table;
	}

	/**
	 * @param string $typeName
	 * @param mixed $default
	 * @return mixed
	 */
	private function normalizeColumnDefaultForType( string $typeName, mixed $default ): mixed {
		if ( !is_string( $default ) ) {
			return $default;
		}
		if ( !in_array( $typeName, [ 'binary', 'blob' ], true ) ) {
			return $default;
		}
		if ( !preg_match( '/^0x([0-9a-fA-F]{2})+$/', $default ) ) {
			return $default;
		}

		$decoded = hex2bin( substr( $default, 2 ) );
		return $decoded !== false ? $decoded : $default;
	}

	/**
	 * @param string $dbName
	 * @return void
	 */
	private function createDatabaseIfMissing( string $dbName ): void {
		if ( !preg_match( '/^[A-Za-z0-9_\\-]+$/', $dbName ) ) {
			throw new RuntimeException( "Invalid database name in import package: {$dbName}" );
		}

		$managementDb = $this->managementDatabaseFactory->getManagementConnection();
		$dbType = $this->normalizeDbType( $managementDb->getType() );
		if ( $dbType === 'mysql' ) {
			$managementDb->query( "CREATE DATABASE IF NOT EXISTS `{$dbName}`", __METHOD__ );
			return;
		}
		if ( $dbType === 'postgres' ) {
			$exists = (bool)$managementDb->newSelectQueryBuilder()
				->select( '1' )
				->from( 'pg_database' )
				->where( [ 'datname' => $dbName ] )
				->caller( __METHOD__ )
				->fetchField();
			if ( !$exists ) {
				$managementDb->query( 'CREATE DATABASE "' . str_replace( '"', '""', $dbName ) . '"', __METHOD__ );
			}
			return;
		}
		$this->logger->error( 'CreateDatabase failed: unsupported database type', [
			'instanceId' => $this->getInstanceId(),
			'dbType' => $dbType,
		] );
		throw new RuntimeException( Message::newFromKey( 'wikifarm-export-error-unsupported-db-type' )->text() );
	}
}
