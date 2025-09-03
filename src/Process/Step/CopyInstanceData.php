<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstanceVaultMirrorIterator;
use Exception;
use MediaWiki\Message\Message;
use Symfony\Component\Filesystem\Filesystem;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class CopyInstanceData extends InstanceAwareStep {

	/** @var ILoadBalancer */
	protected $lb;

	/** @var InstanceEntity */
	protected $sourceInstance;

	/** @var IDatabase */
	private $rootDatabase;

	/** @var string[] */
	protected $skipTableData = [ 'objectcache', 'module_deps', 'l10n_cache', 'bs_whoisonline', 'updatelog' ];

	/** @var string[] */
	protected $filesToSkip = [ 'extensions/BlueSpiceFoundation/data/.smw.json' ];

	/**
	 * @param InstanceManager $instanceManager
	 * @param ILoadBalancer $lb
	 * @param string $instanceId
	 * @param string $sourceInstanceId
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, ILoadBalancer $lb, string $instanceId, string $sourceInstanceId
	) {
		parent::__construct( $instanceManager, $instanceId );
		$this->lb = $lb;
		$this->sourceInstance = $instanceManager->getStore()->getInstanceById( $sourceInstanceId );
		if ( !$this->sourceInstance ) {
			throw new Exception( Message::newFromKey( 'wikifarm-error-source-instance-not-ready' )->text() );
		}
	}

	/** @inheritDoc */
	public function execute( $data = [] ): array {
		$this->copyDB();
		$this->copyData();
		return $data;
	}

	private function copyDB() {
		$this->rootDatabase = $this->lb->getConnection( DB_PRIMARY );

		$sourceTables = $this->getTables( $this->sourceInstance );
		$targetTables = $this->getTables( $this->getInstance() );

		$sourceDbName = $this->sourceInstance->getDbName();
		$targetDbName = $this->getInstance()->getDbName();

		$this->rootDatabase->query( "SET FOREIGN_KEY_CHECKS=0;", __METHOD__ );
		foreach ( $sourceTables as $tableKey => $sourceTableName ) {
			if ( isset( $targetTables[$tableKey] ) ) {
				$this->rootDatabase->query( "DROP TABLE `$targetDbName`.`$targetTables[$tableKey]`", __METHOD__ );
			}

			$targetTables[$tableKey] = $this->getInstance()->getDbPrefix() . $tableKey;
			$this->rootDatabase->query(
				"CREATE TABLE `$targetDbName`.`$targetTables[$tableKey]` LIKE `$sourceDbName`.`$sourceTableName`",
				__METHOD__
			);

			if ( in_array( $tableKey, $this->skipTableData ) ) {
				continue;
			}

			$this->rootDatabase->query(
				"REPLACE INTO `$targetDbName`.`$targetTables[$tableKey]` SELECT * FROM `$sourceDbName`.`$sourceTableName`",
				__METHOD__
			);
		}
		$this->rootDatabase->query( "SET FOREIGN_KEY_CHECKS=1;", __METHOD__ );
	}

	private function copyData() {
		$sourceDir = $this->sourceInstance->getVault( $this->getInstanceManager()->getFarmConfig() );
		$targetDir = $this->getInstance()->getVault( $this->getInstanceManager()->getFarmConfig() );
		$time = time();
		$tmpTarget = "$targetDir.$time";

		$fileSystem = new Filesystem();
		$fileSystem->rename( $targetDir, $tmpTarget );
		$iterator = new InstanceVaultMirrorIterator( $sourceDir );
		$fileSystem->mirror( $sourceDir, $targetDir, $iterator );

		foreach ( $this->filesToSkip as $relFilePathname ) {
			// If file would exist without the copying, keep that version of the file
			if ( $fileSystem->exists( "$tmpTarget/$relFilePathname" ) ) {
				$fileSystem->copy(
					"$tmpTarget/$relFilePathname",
					"$targetDir/$relFilePathname",
					true
				);
				continue;
			}
			// If file would not exists if not for the copying, remove the file
			$fileSystem->remove( "$targetDir/$relFilePathname" );
		}
		$fileSystem->remove( $tmpTarget );
	}

	/**
	 * @param InstanceEntity $instance
	 * @return array
	 */
	protected function getTables( InstanceEntity $instance ): array {
		$tables = [];
		$res = $this->rootDatabase->query( "SHOW FULL TABLES FROM `{$instance->getDbName()}` WHERE TABLE_TYPE NOT LIKE 'VIEW'", __METHOD__ );
		while ( $row = $res->fetchRow() ) { //phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
			$tableName = $row[0];
			if ( $instance->getDbPrefix() && strpos( $tableName, $instance->getDbPrefix() ) !== 0 ) {
				continue;
			}
			$unprefixedTableName = preg_replace( "/^{$instance->getDbPrefix()}/", '', $row[0] );
			$tables[$unprefixedTableName] = $tableName;
		}

		return $tables;
	}
}
