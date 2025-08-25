<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use Ifsnop\Mysqldump\Mysqldump;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Wikimedia\Rdbms\IDatabase;
use ZipArchive;

class ArchiveInstance extends InstanceAwareStep {

	/** @var string */
	protected $archiveDirectory;
	/** @var \ZipArchive */
	private $zip;
	/** @var Config */
	protected $mainConfig;

	/** @var bool */
	private $sharedDBSetup = false;

	/** @var string[] */
	private $skipFolders = [ 'thumb', 'temp', 'cache' ];

	/** @var string */
	private $tmpDumpFilepath = '';

	public function __construct( InstanceManager $instanceManager, Config $mainConfig, string $instanceId ) {
		parent::__construct( $instanceManager, $instanceId );
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @param array $data
	 * @return array
	 */
	public function execute( $data = [] ): array {
		$this->archiveDirectory = $this->getInstanceManager()->getFarmConfig()->get( 'archiveDirectory' );
		if ( !file_exists( $this->archiveDirectory ) ) {
			wfMkdirParents( $this->archiveDirectory );
		}

		$this->getInstanceManager()->getLogger()->info(
			"Archiving instance {path} ...",
			[ 'path' => $this->getInstance()->getPath() ]
		);

		$this->setSharedSetupFlag();

		$this->initZipFile();
		$this->dumpDatabase();
		$this->addInstanceVault();
		$this->zip->close();
		$this->cleanUp();
		wfRecursiveRemoveDir( $this->getInstance()->getVault( $this->getInstanceManager()->getFarmConfig() ) );
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
		$this->zip = new ZipArchive();
		$this->zip->open(
			$destFilePath,
			ZipArchive::CREATE | ZipArchive::OVERWRITE
		);
	}

	/**
	 * @return string
	 */
	private function makeDestFilepath(): string {
		$timestamp = date( 'YmdHis' );
		return "{$this->archiveDirectory}/{$this->getInstance()->getPath()}-$timestamp.zip";
	}

	private function addInstanceVault() {
		$vault = $this->getInstance()->getVault( $this->getInstanceManager()->getFarmConfig() );
		$vaultDir = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $vault ) );
		$filesToBackup = [];
		$this->getInstanceManager()->getLogger()->info( "Archiving instance directory..." );

		foreach ( $vaultDir as $fileInfo ) {
			$fileInfo instanceof SplFileInfo;
			if ( $fileInfo->isDir() ) {
				continue;
			}
			$blacklisted = false;
			foreach ( $this->skipFolders as $folder ) {
				$skipBasePath = "$vault/images/$folder";
				if ( strpos( $fileInfo->getPathname(), $skipBasePath ) === 0 ) {
					$blacklisted = true;
					break;
				}
			}
			if ( $blacklisted ) {
				continue;
			}
			$filesToBackup[] = $fileInfo->getPathname();
		}

		$pregPattern = '#^' . preg_quote( "$vault/" ) . '#';
		foreach ( $filesToBackup as $path ) {
			$localPath = preg_replace( $pregPattern, '', $path );
			$this->zip->addFile( $path, $localPath );
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

		$tmpPath = sys_get_temp_dir();
		$this->tmpDumpFilepath = "$tmpPath/$dbName.sql";

		$dump->start( $this->tmpDumpFilepath );

		$localPath = "$dbName.sql";
		$this->zip->addFile( $this->tmpDumpFilepath, $localPath );
	}

	private function cleanUp() {
		unlink( $this->tmpDumpFilepath );
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
