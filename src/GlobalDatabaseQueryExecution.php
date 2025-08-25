<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use Psr\Log\LoggerInterface;
use Throwable;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DatabaseFactory;
use Wikimedia\Rdbms\ILoadBalancer;

/**
 * This class can execute a given query on every instance in the farm and return unified results
 *
 */
class GlobalDatabaseQueryExecution {

	/**
	 * @var ManagementDatabaseFactory
	 */
	private $databaseFactory;

	/**
	 * @var DirectInstanceStore
	 */
	private $instanceStore;
	/** @var ILoadBalancer */
	private $lb;
	/** @var Config */
	private $farmConfig;
	/** @var LoggerInterface */
	private $logger;
	/** @var IAccessStore */
	private $accessStore;
	/** @var bool */
	private $isSharingUsers;
	/** @var InstanceEntity[]|null */
	private $instances = null;
	/** @var Database[]|null */
	private $instanceDatabases = null;

	/**
	 * @param ManagementDatabaseFactory $databaseFactory
	 * @param DirectInstanceStore $instanceStore
	 * @param ILoadBalancer $lb
	 * @param Config $farmConfig
	 * @param LoggerInterface $logger
	 * @param IAccessStore $accessStore
	 * @param bool $isSharingUsers
	 */
	public function __construct(
		ManagementDatabaseFactory $databaseFactory, DirectInstanceStore $instanceStore, ILoadBalancer $lb,
		Config $farmConfig, LoggerInterface $logger, IAccessStore $accessStore, bool $isSharingUsers
	) {
		$this->databaseFactory = $databaseFactory;
		$this->instanceStore = $instanceStore;
		$this->lb = $lb;
		$this->farmConfig = $farmConfig;
		$this->logger = $logger;
		$this->accessStore = $accessStore;
		$this->isSharingUsers = $isSharingUsers;
	}

	/**
	 * @param string|array $table
	 * @param array $vars
	 * @param array $conds
	 * @param string $fname
	 * @param array $options
	 * @param array $join_conds
	 * @param array|null $instances List of InstanceEntity objects to query. If null, query in all available instances
	 * @return array
	 */
	public function select(
		$table, $vars, $conds = '', $fname = __METHOD__, $options = [], $join_conds = [], ?array $instances = []
	) {
		if ( !$this->isSharingUsers ) {
			// Execute just a query for the local instance, as we cannot quickly and reliably check
			// user permission on other instances if users are not shared
			$localDb = $this->lb->getConnection( DB_REPLICA );
			$currentInstance = $this->instanceStore->getCurrentInstance();
			if ( !$currentInstance ) {
				return [];
			}
			return $this->combine( [
				$currentInstance->getPath() => $localDb->select( $table, $vars, $conds, $fname, $options, $join_conds )
			] );
		}
		$managementDb = $this->databaseFactory->createManagementConnection();
		$instancePaths = null;
		if ( $instances ) {
			$instancePaths = [];
			foreach ( $instances as $instance ) {
				if ( !$instance instanceof InstanceEntity ) {
					throw new \InvalidArgumentException( 'Expected InstanceEntity' );
				}
				$instancePaths[] = $instance->getPath();
			}
		}
		$this->assertInstanceDatabases( $managementDb, $instancePaths );
		$options['LIMIT'] = $options['LIMIT'] ?? 10;
		$results = [
			'w' => $managementDb->select( $table, $vars, $conds, $fname, $options, $join_conds ),
		];

		foreach ( $this->instanceDatabases as $path => $db ) {
			try {
				$results[$path] = $db->select( $table, $vars, $conds, $fname, $options, $join_conds );
			} catch ( Throwable $ex ) {
				$this->logger->error( "Global query failed for instance \"{instance}\": {error}", [
					'instance' => $path,
					'error' => $ex->getMessage(),
				] );
			}
		}

		return $this->combine( $results );
	}

	/**
	 * @param InstanceEntity|null $sharedInstance
	 * @param Title $title
	 * @return array|null
	 */
	public function getForeignPage( ?InstanceEntity $sharedInstance, Title $title ): ?array {
		$instancePath = $sharedInstance->getPath();
		$this->assertInstanceDatabases( $this->databaseFactory->createManagementConnection(), [ $instancePath ] );
		$db = $this->instanceDatabases[$instancePath] ?? null;
		if ( !$db ) {
			return null;
		}
		$row = $db->newSelectQueryBuilder()
			->select( [ 'old_text', 'page_latest', 'page_id', 'page_namespace', 'page_title' ] )
			->from( 'text' )
			->join( 'content', 'c', [ 'old_id = \'tt:\' + content_id' ] )
			->join( 'slots', 'sl', [ 'slot_content_id = content_id' ] )
			->join( 'page', 'p', [ 'slot_revision_id = page_latest' ] )
			->where( [
				'page_title' => $title->getText(),
				'page_namespace' => $title->getNamespace(),
			] )
			->fetchRow();

		if ( !$row ) {
			return null;
		}

		return [
			'content' => $row->old_text,
			'id' => $row->page_id,
			'revision' => $row->page_latest,
			'namespace' => $row->page_namespace,
			'title' => $row->page_title,
		];
	}

	/**
	 * @param array $results
	 * @return array
	 */
	private function combine( array $results ): array {
		$combinedResults = [];
		foreach ( $results as $instance => $resultSet ) {
			if ( !isset( $this->instances[$instance] ) ) {
				continue;
			}
			foreach ( $resultSet as $row ) {
				$row->_instance = $instance;
				$row->_instance_interwiki = mb_strtolower( $instance );
				$row->_instance_display = $this->instances[$instance]->getDisplayName();
				$row->_is_local_instance =
					$this->instanceStore->getCurrentInstance() &&
					$instance === $this->instanceStore->getCurrentInstance()->getPath();
				$combinedResults[] = $row;
			}
		}
		return $combinedResults;
	}

	private function assertInstanceDatabases( Database $managementDb, ?array $instances = null ) {
		$user = RequestContext::getMain()->getUser();
		if ( !$user ) {
			$this->instances = [];
			return;
		}
		$accessiblePaths = $instances ?? $this->accessStore->getInstancePathsWhereUserHasRole(
			$user, 'reader'
		);
		if ( $this->instances === null ) {
			$this->instances = [];
			if ( in_array( 'w', $accessiblePaths ) ) {
				$this->instances['w'] = new RootInstanceEntity();
			}
			$this->instanceDatabases = [];
			$instances = $this->instanceStore->getAllInstances();
			foreach ( $instances as $instance ) {
				$isCurrent = $this->instanceStore->getCurrentInstance() &&
					$instance->getPath() === $this->instanceStore->getCurrentInstance()->getPath();
				// TODO: We should probably check for local `reader` role here, but in general,
				// if you are already on an instance, you can obviously read it
				if ( !in_array( $instance->getPath(), $accessiblePaths ) && !$isCurrent	) {
					continue;
				}
				if ( !$instance->isActive() ) {
					continue;
				}
				if ( $instance->getMetadata()['notsearchable'] ?? false ) {
					continue;
				}
				$db = ( new DatabaseFactory() )->create(
					$managementDb->getType(), [
						'host' => $this->farmConfig->get( 'managementDBserver' ),
						'user' => $this->farmConfig->get( 'managementDBuser' ),
						'password' => $this->farmConfig->get( 'managementDBpassword' ),
						'tablePrefix' => $instance->getDbPrefix(),
						'dbname' => $instance->getDbName()
					]
				);
				if ( $db ) {
					$this->instances[$instance->getPath()] = $instance;
					$this->instanceDatabases[$instance->getPath()] = $db;
				}
			}
		}
	}
}
