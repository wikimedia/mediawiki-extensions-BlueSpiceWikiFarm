<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Storage\InstanceTransaction;
use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use Wikimedia\FileBackend\FileBackend;
use Wikimedia\Rdbms\IDatabase;
use ZipArchive;

class ArchiveInstance extends InstanceAwareStep {

	/** @var \ZipArchive */
	private $zip;
	/** @var Config */
	protected $mainConfig;
	/** @var StorageHandler */
	protected StorageHandler $storageHandler;

	/** @var bool */
	private $sharedDBSetup = false;

	/** @var string[] */
	private $skipFolders = [ 'thumb/', 'temp/', 'cache/', 'images/thumb/', 'images/temp/' ];

	/** @var string */
	private $tmpDumpFilepath = '';

	/** @var string */
	private $zipFilename = '';

	/** @var string */
	private $zipLocation = '';

	/** @var array */
	private $tempFilesToBackup = [];

	/** @var FileBackend */
	private $storageBackend;

	public function __construct(
		InstanceManager $instanceManager, Config $mainConfig, StorageHandler $storageHandler, string $instanceId
	) {
		parent::__construct( $instanceManager, $instanceId );
		$this->mainConfig = $mainConfig;
		$this->storageHandler = $storageHandler;
	}

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		$this->getInstanceManager()->getLogger()->info(
			"Archiving instance {path} ...",
			[ 'path' => $this->getInstance()->getPath() ]
		);

		$this->storageBackend = $this->storageHandler->getBackend(
			$this->getInstanceManager()->getFarmConfig()->get( 'instanceStorageBackend' )
		);

		$this->setSharedSetupFlag();

		$this->initZipFile();
		$this->dumpDatabase();
		$this->addInstanceVault();
		$this->zip->close();

		$status = ( new InstanceTransaction( $this->storageBackend ) )
			->storeToArchive(
				$this->zipLocation,
				$this->zipFilename
			)->commit();

		$this->cleanUp();
		if ( !$status->isOK() ) {
			throw new Exception( Message::newFromKey( 'wikifarm-error-archive-instance-failed' )->text() );
		}
		// If it fails, it fails
		( new InstanceTransaction( $this->storageBackend ) )
			->deleteInstanceDirectory( $this->getInstance()->getPath() )
			->commit();
		$this->dropInstanceDatabase();

		return array_merge( $data, [ 'success' => true ] );
	}

	protected function setSharedSetupFlag() {
		if (
			$this->mainConfig->get( 'DBname' ) === $this->getInstance()->getDbName() &&
			$this->getInstance()->getDbPrefix() &&
			$this->getInstance()->getDbPrefix() !== $this->mainConfig->get( 'DBprefix' )
		) {
			$this->sharedDBSetup = true;
		}
		if ( !$this->sharedDBSetup && $this->mainConfig->get( 'DBname' ) === $this->getInstance()->getDbName() ) {
			// Short-circuit, someone doing something wrong
			$this->getInstanceManager()->getLogger()->error(
				"Trying to drop the main database for a non-shared db setup!"
			);
			throw new Exception( Message::newFromKey( 'wikifarm-error-instance-db-not-ready' )->text() );
		}
	}

	private function initZipFile() {
		$destFilePath = $this->makeDestFilepath();
		$this->getInstanceManager()->getLogger()->info(
			"Creating an archive at {path} ...",
			[ 'path' => $destFilePath ]
		);
		$this->zipLocation = $destFilePath;
		$this->zip = new ZipArchive();
		$this->zip->open(
			$this->zipLocation,
			ZipArchive::CREATE | ZipArchive::OVERWRITE
		);
	}

	/**
	 * @return string
	 */
	private function makeDestFilepath(): string {
		$timestamp = date( 'YmdHis' );
		$this->zipFilename = "{$this->getInstance()->getPath()}-$timestamp.zip";
		return $this->storageHandler->getTempFilePath( $this->zipFilename );
	}

	private function addInstanceVault() {
		$transaction = new InstanceTransaction( $this->storageBackend );
		$files = $this->storageBackend->getFileList( [
			'dir' => $transaction->makeInstancePath( $this->getInstance()->getPath() ),
			'topOnly' => false,
		] );
		$this->getInstanceManager()->getLogger()->info( "Archiving instance directory..." );

		$filesToBackup = [];
		foreach ( $files as $file ) {
			$blacklisted = false;
			foreach ( $this->skipFolders as $folder ) {
				if ( str_starts_with( $file, $folder ) ) {
					$blacklisted = true;
					break;
				}
			}
			if ( $blacklisted ) {
				continue;
			}
			$filesToBackup[] = $file;
		}

		foreach ( $filesToBackup as $path ) {
			$file = $this->storageBackend->getLocalCopy( [
				'src' => $transaction->makeInstancePath( $this->getInstance()->getPath(), $path ),
			] );
			if ( !$file ) {
				$this->getInstanceManager()->getLogger()->warning(
					"Could not get local copy of file {file}", [ 'file' => $path ]
				);
				continue;
			}
			// ZIPArchive only processes files on `close()`!
			// This means we need to keep temp files alive until after `close()`
			// When all references to the file are gone, file is removed, so to prevent it
			// we store them in a class member
			$this->tempFilesToBackup[] = $file;
			// throw new Exception( json_encode( array_map( fn( $x ) => $x->getPath(), $this->tempFilesToBackup ), JSON_PRETTY_PRINT ) );
			$this->zip->addFile( $file->getPath(), $path );
		}
	}

	private function dumpDatabase() {
		$this->getInstanceManager()->getLogger()->info( "Dumping database..." );

		$dbServer = $this->mainConfig->get( 'DBserver' );
		$dbUser = $this->mainConfig->get( 'DBuser' );
		$dbPassword = $this->mainConfig->get( 'DBpassword' );
		$dbName = $this->getInstance()->getDBName();
		$dumpOptions = [];

		if ( $this->sharedDBSetup ) {
			// Shared DB Setup
			$dumpOptions['include-tables'] = $this->getTablesForSharedDb();
		}
		$dump = new Mysqldump(
			"mysql:host=$dbServer;dbname=$dbName",
			$dbUser,
			$dbPassword,
			$dumpOptions
		);

		$this->tmpDumpFilepath = $this->storageHandler->getTempFilePath( "$dbName.sql" );

		$dump->start( $this->tmpDumpFilepath );

		$localPath = "$dbName.sql";
		$this->zip->addFile( $this->tmpDumpFilepath, $localPath );
	}

	private function cleanUp() {
		$this->storageHandler->newTransaction( true )
			->delete( $this->zipFilename, '' )
			->commit();
		$this->storageHandler->newTransaction( true )
			->delete( "{$this->getInstance()->getDBName()}.sql", '' )
			->commit();
		// This actually releases temp files, removing them
		unset( $this->tempFilesToBackup );
	}

	/**
	 * @return void
	 */
	protected function dropInstanceDatabase() {
		$db = $this->getInstanceManager()->getDatabaseConnectionForInstance( $this->getInstance() );
		if ( !$db ) {
			$this->getInstanceManager()->getLogger()->error( "Drop database failed: cannot get connection" );
			return;
		}
		if ( $this->sharedDBSetup ) {
			// Allow next query to return a long response
			$db->query( "SET SESSION group_concat_max_len = 1000000", __METHOD__ );
			// DROP VIEWS
			$this->executeGeneratedStatement(
				"select concat( 'drop view ', TABLE_SCHEMA, '.', TABLE_NAME, ';') as statement " .
				"from information_schema.views where table_name like '{$this->getInstance()->getDbPrefix()}%';",
				$db
			);
			// DROP TABLES
			$this->executeGeneratedStatement(
				"SELECT CONCAT( 'DROP TABLE ', GROUP_CONCAT( table_name ) , ';' ) AS statement" .
				" FROM information_schema.tables  WHERE table_name LIKE '{$this->getInstance()->getDbPrefix()}%'" .
				" AND TABLE_TYPE NOT LIKE 'VIEW'",
				$db
			);
		} else {
			try {
				$db->query( "DROP DATABASE {$this->getInstance()->getDBName()}", __METHOD__ );
			} catch ( \Throwable $e ) {
				$this->getInstanceManager()->getLogger()->error( "Drop database failed: {error}", [ 'error' => $e->getMessage() ] );
			}
		}
	}

	/**
	 * @param string $generationQuery
	 * @param IDatabase $db
	 * @return void
	 */
	private function executeGeneratedStatement( string $generationQuery, IDatabase $db ) {
		$res = $db->query( $generationQuery, __METHOD__ );
		$row = $res->fetchRow();
		$dropStatement = $row['statement'];
		if ( $dropStatement ) {
			$db->query( $dropStatement, __METHOD__ );
		}
	}

	/**
	 * @return array
	 */
	private function getTablesForSharedDb(): array {
		$tables = [];
		$res = $this->getInstanceManager()->getDatabaseConnectionForInstance( $this->getInstance() )->query(
			"SHOW FULL TABLES FROM `{$this->getInstance()->getDBName()}` WHERE TABLE_TYPE NOT LIKE 'VIEW'",
			__METHOD__
		);
		while ( $row = $res->fetchRow() ) { //phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$tableName = $row[0];
			if ( strpos( $tableName, $this->getInstance()->getDbPrefix() ) !== 0 ) {
				continue;
			}
			$tables[] = $tableName;
		}
		return $tables;
	}

}
