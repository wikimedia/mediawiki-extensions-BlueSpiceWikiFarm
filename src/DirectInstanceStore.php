<?php

namespace BlueSpice\WikiFarm;

use DateTime;
use LogicException;
use RuntimeException;
use Throwable;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * No-service dependency provider for instances
 * Always connects to the management database
 *
 */
class DirectInstanceStore {

	/**
	 * @var ManagementDatabaseFactory
	 */
	private ManagementDatabaseFactory $databaseFactory;

	/** @var InstanceEntity[]|null */
	private ?array $instances = null;

	/**
	 * @param ManagementDatabaseFactory $databaseFactory
	 */
	public function __construct( ManagementDatabaseFactory $databaseFactory ) {
		$this->databaseFactory = $databaseFactory;
	}

	/**
	 * @return Database
	 */
	protected function getDB(): Database {
		return $this->databaseFactory->createManagementConnection();
	}

	/**
	 * @param InstanceEntity $instance
	 * @return void
	 *
	 */
	public function store( InstanceEntity $instance ) {
		if ( $instance instanceof RootInstanceEntity ) {
			throw new LogicException( 'Root instance cannot be stored' );
		}
		$db = $this->getDB();
		try {
			$this->load( $db );
			$exists = isset( $this->instances[$instance->getId()] );
			$serialized = $instance->dbSerialize();
			if ( $exists ) {
				unset( $serialized['sfi_id'] );
				$db->update(
					'simple_farmer_instances',
					$serialized,
					[ 'sfi_id' => $instance->getId() ],
					__METHOD__
				);
			} else {
				$db->insert(
					'simple_farmer_instances',
					$serialized,
					__METHOD__
				);
			}
		} catch ( Throwable $ex ) {
			throw new RuntimeException( 'Failed to store instance', 500, $ex );
		} finally {
			$this->reloadInstance( $instance->getId(), $db );
			$db->close( __METHOD__ );
		}
	}

	/**
	 * @return InstanceEntity[]
	 */
	public function getAllInstances(): array {
		$this->load();
		return array_values( $this->instances ?? [] );
	}

	/**
	 * @param string $instanceIdentifier
	 * @return InstanceEntity|null
	 */
	public function getInstanceByIdOrPath( string $instanceIdentifier ): ?InstanceEntity {
		return $this->getInstanceById( $instanceIdentifier ) ?? $this->getInstanceByPath( $instanceIdentifier );
	}

	/**
	 * @return array
	 */
	public function getWikiMap(): array {
		$map = [];
		$this->load();
		foreach ( $this->instances as $instance ) {
			$map[$instance->getWikiId()] = $instance;
		}
		return $map;
	}

	/**
	 * @param string $id
	 * @return InstanceEntity|null
	 */
	public function getInstanceById( string $id ) {
		$this->load();
		return $this->instances[$id] ?? null;
	}

	/**
	 * @param string $path
	 * @return InstanceEntity|null
	 */
	public function getInstanceByPath( string $path ): ?InstanceEntity {
		if ( !$path ) {
			return null;
		}
		if ( $path === 'w' || $path === 'wiki' ) {
			return new RootInstanceEntity();
		}
		$this->load();
		foreach ( $this->instances as $instance ) {
			if ( $instance->getPath() === $path ) {
				return $instance;
			}
		}
		return null;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return void
	 */
	public function removeEntry( InstanceEntity $instance ) {
		$db = $this->getDB();
		$db->delete(
			'simple_farmer_instances',
			[ 'sfi_id' => $instance->getId() ],
			__METHOD__
		);
		$db->close( __METHOD__ );
		if ( isset( $this->instances[$instance->getId()] ) ) {
			unset( $this->instances[$instance->getId()] );
		}
	}

	/**
	 * @param array $conds
	 * @param array $options
	 * @return InstanceEntity|null
	 */
	protected function getOne( array $conds = [], array $options = [] ): ?InstanceEntity {
		$res = $this->doQuery( $conds, $options );
		if ( $res->numRows() === 0 ) {
			return null;
		}
		return $this->rowToInstance( $res->fetchObject() );
	}

	/**
	 * @param array $conds
	 * @param array $options
	 * @param array $fields
	 * @return IResultWrapper
	 */
	protected function doQuery( array $conds = [], array $options = [], array $fields = [] ): IResultWrapper {
		$db = $this->getDB();
		if (
			MW_ENTRY_POINT === 'cli' &&
			!$db->tableExists( 'simple_farmer_instances', __METHOD__ )
		) {
			// Special case, for farm installation, no table exists yet
			return new FakeResultWrapper( [] );
		}
		if ( empty( $fields ) ) {
			$fields = [ '*' ];
		}

		$res = $db->newSelectQueryBuilder()
			->from( 'simple_farmer_instances' )
			->select( $fields )
			->where( $conds )
			->caller( __METHOD__ )
			->options( $options )
			->fetchResultSet();
		$db->close();
		return $res;
	}

	/**
	 * @param \stdClass $object
	 * @return InstanceEntity|null
	 */
	private function rowToInstance( $object ): ?InstanceEntity {
		if ( !$object ) {
			return null;
		}

		$args = [
			$object->sfi_id,
			$object->sfi_path,
			$object->sfi_display_name,
			DateTime::createFromFormat( 'YmdHis', $object->sfi_created ),
			DateTime::createFromFormat( 'YmdHis', $object->sfi_touched ),
			$object->sfi_status,
			$object->sfi_database,
			$object->sfi_db_prefix,
			json_decode( $object->sfi_meta, 1 ),
			json_decode( $object->sfi_config, 1 ),
			$object->sfr_wiki_id ?? ''
		];

		if ( str_starts_with( $object->sfi_path, '-' ) ) {
			return new SystemInstanceEntity( ...$args );
		}

		return new InstanceEntity( ...$args );
	}

	/**
	 * @return array
	 */
	public function getInstanceIds(): array {
		$this->load();
		return array_keys( $this->instances ?? [] );
	}

	/**
	 * @param string $path
	 * @return bool
	 */
	public function pathAvailable( string $path ): bool {
		if ( $path === 'w' || $path === 'wiki' ) {
			return false;
		}
		$this->load();
		foreach ( $this->instances as $instance ) {
			if ( $instance->getPath() === $path ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Check if instance with such name already exists
	 *
	 * @param string $name
	 * @return bool
	 */
	public function nameExists( string $name ): bool {
		$this->load();
		foreach ( $this->instances as $instance ) {
			if ( $instance->getDisplayName() === $name ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param string $instanceId
	 * @param string $pid
	 * @param string $type
	 * @return void
	 */
	public function storeRunningProcess( string $instanceId, string $pid, string $type ) {
		$db = $this->getDB();
		$db->insert(
			'simple_farmer_processes',
			[
				'sfp_instance' => $instanceId,
				'sfp_pid' => $pid,
				'sfp_type' => $type,
				'sfp_started' => wfTimestamp( TS_MW ),
			],
			__METHOD__
		);
		$db->close( __METHOD__ );
	}

	/**
	 * @param InstanceEntity $instance
	 * @return void
	 */
	public function clearRunningProcesses( InstanceEntity $instance ) {
		$db = $this->getDB();
		$db->delete(
			'simple_farmer_processes',
			[ 'sfp_instance' => $instance->getId() ],
			__METHOD__
		);
		$db->close( __METHOD__ );
	}

	/**
	 * @return InstanceEntity|null
	 */
	public function getCurrentInstance(): ?InstanceEntity {
		if ( defined( 'FARMER_IS_ROOT_WIKI_CALL' ) && FARMER_IS_ROOT_WIKI_CALL ) {
			return new RootInstanceEntity();
		}
		if ( defined( 'FARMER_CALLED_INSTANCE' ) && FARMER_CALLED_INSTANCE ) {
			return $this->getInstanceByIdOrPath( FARMER_CALLED_INSTANCE );
		}
		return null;
	}

	/**
	 * @param array $conds
	 * @return array
	 */
	public function getInstancePathsQuick( array $conds = [] ): array {
		$res = $this->doQuery( $conds, [], [ 'sfi_path' ] );
		$paths = [];
		foreach ( $res as $row ) {
			$paths[] = $row->sfi_path;
		}
		return $paths;
	}

	/**
	 * @param Database|null $db
	 * @param bool $reload
	 * @return void
	 */
	private function load( ?Database $db = null, bool $reload = false ) {
		if ( $this->instances === null || $reload ) {
			$this->instances = [];
			$shouldCloseConnection = $db === null;
			$db = $db ?? $this->getDB();
			if (
				MW_ENTRY_POINT === 'cli' &&
				!$db->tableExists( 'simple_farmer_instances', __METHOD__ )
			) {
				if ( $shouldCloseConnection ) {
					$db->close( __METHOD__ );
				}
				return;
			}
			$res = $db->newSelectQueryBuilder()
				->from( 'simple_farmer_instances' )
				->select( '*' )
				->caller( __METHOD__ )
				->fetchResultSet();

			if ( $shouldCloseConnection ) {
				$db->close( __METHOD__ );
			}
			foreach ( $res as $row ) {
				$instance = $this->rowToInstance( $row );
				if ( !$instance ) {
					continue;
				}
				$this->instances[$instance->getId()] = $instance;
			}
		}
	}

	/**
	 * @param string $instanceId
	 * @param Database|null $db
	 * @return void
	 */
	public function reloadInstance( string $instanceId, ?Database $db = null ) {
		if ( $this->instances === null ) {
			$this->load( $db );
			return;
		}
		$shouldCloseConnection = $db === null;
		$db = $db ?? $this->getDB();
		if (
			MW_ENTRY_POINT === 'cli' &&
			!$db->tableExists( 'simple_farmer_instances', __METHOD__ )
		) {
			if ( $shouldCloseConnection ) {
				$db->close( __METHOD__ );
			}
			return;
		}

		$res = $db->newSelectQueryBuilder()
			->from( 'simple_farmer_instances' )
			->select( '*' )
			->where( [ 'sfi_id' => $instanceId ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( $shouldCloseConnection ) {
			$db->close( __METHOD__ );
		}
		$instance = $this->rowToInstance( $res );
		if ( !$instance ) {
			return;
		}
		$this->instances[$instance->getId()] = $instance;
	}
}
