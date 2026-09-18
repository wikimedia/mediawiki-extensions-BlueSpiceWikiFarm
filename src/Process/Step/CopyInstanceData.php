<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Storage\InstanceTransaction;
use Exception;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use Throwable;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class CopyInstanceData extends InstanceAwareStep {

	/** @var ILoadBalancer */
	protected $lb;

	/** @var InstanceEntity */
	protected $sourceInstance;

	/** @var IDatabase */
	private $rootDatabase;

	/** @var StorageHandler */
	protected StorageHandler $storageHandler;

	/** @var string[] */
	protected $skipTableData = [
		'objectcache', 'module_deps', 'l10n_cache', 'bs_whoisonline', 'updatelog',
		'oauth2_access_tokens', 'oauth_accepted_consumer', 'oauth_registered_consumer'
	];

	/**
	 * @param InstanceManager $instanceManager
	 * @param ILoadBalancer $lb
	 * @param StorageHandler $storageHandler
	 * @param string $instanceId
	 * @param string $sourceInstanceId
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, ILoadBalancer $lb, StorageHandler $storageHandler,
		string $instanceId, string $sourceInstanceId
	) {
		parent::__construct( $instanceManager, $instanceId );
		$this->lb = $lb;
		$this->sourceInstance = $instanceManager->getStore()->getInstanceById( $sourceInstanceId );
		if ( !$this->sourceInstance ) {
			throw new Exception( Message::newFromKey( 'wikifarm-error-source-instance-not-ready' )->text() );
		}
		$this->storageHandler = $storageHandler;
	}

	/** @inheritDoc */
	public function execute( $data = [] ): array {
		$this->copyDB();
		$this->copyData();
		try {
			$this->copyDynamicConfig();
		} catch ( Throwable $e ) {
			$data['copy-dynamic-config-error'] = $e->getMessage();
		}

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
		$backend = $this->storageHandler->getBackend(
			$this->getInstanceManager()->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		$status = ( new InstanceTransaction( $backend ) )
			->copyInstance(
				$this->sourceInstance->getPath(),
				$this->getInstance()->getPath()
			)->commit();

		if ( !$status->isOK() ) {
			throw new Exception(
				Message::newFromKey( 'wikifarm-error-copy-instance-data-failed' )->text()
			);
		}
	}

	/**
	 * @return void
	 */
	private function copyDynamicConfig() {
		$sourceWikiId = $this->sourceInstance->getWikiId();
		$targetWikiId = $this->getInstance()->getWikiId();
		// Copy all rows from `mwstake_dynamic_config` where `mwdc_wiki_id` = $sourceWikiId to `mwstake_dynamic_config` where `mwdc_wiki_id` = $targetWikiId
		$rows = $this->rootDatabase->newSelectQueryBuilder()
			->select( '*' )
			->from( 'mwstake_dynamic_config' )
			->where( [ 'mwdc_wiki_id' => $sourceWikiId ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$newRows = [];
		foreach ( $rows as $row ) {
			$newRow = (array)$row;
			$newRow['mwdc_wiki_id'] = $targetWikiId;
			$newRows[] = $newRow;
		}
		if ( !empty( $newRows ) ) {
			$this->rootDatabase->newInsertQueryBuilder()
				->insert( 'mwstake_dynamic_config' )
				->rows( $newRows )
				->caller( __METHOD__ )
				->execute();
		}
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
