<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use InvalidArgumentException;
use Wikimedia\Rdbms\ILoadBalancer;

class WikiAccessLookup {

	private const TABLE = 'wiki_access_levels';

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct(
		private readonly ILoadBalancer $lb
	) {
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string
	 */
	public function getAccessLevelForInstance( InstanceEntity $instance ): string {
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db || !$db->tableExists( static::TABLE ) ) {
			return 'private';
		}
		$row = $db->newSelectQueryBuilder()
			->select( 'wal_level' )
			->from( static::TABLE )
			->where( [ 'wal_instance' => $instance->getId() ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row || !in_array( $row->wal_level, GroupAccessStore::ACCESS_LEVELS ) ) {
			return $this->getLegacyAccessLevel( $instance );
		}

		return $row->wal_level;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return bool
	 */
	public function hasAccessLevelForInstance( InstanceEntity $instance ): bool {
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db ) {
			return false;
		}
		return $db->newSelectQueryBuilder()
			->select( 'wal_instance' )
			->from( static::TABLE )
			->where( [ 'wal_instance' => $instance->getId() ] )
			->caller( __METHOD__ )
			->fetchField() !== false;
	}

	/**
	 * @param InstanceEntity $instance
	 * @param string $level
	 * @return void
	 */
	public function setAccessLevelForInstance( InstanceEntity $instance, string $level ): void {
		$this->assertValidAccessLevel( $level );
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db ) {
			return;
		}

		$db->startAtomic( __METHOD__ );
		$db->newDeleteQueryBuilder()
			->delete( static::TABLE )
			->where( [ 'wal_instance' => $instance->getId() ] )
			->caller( __METHOD__ )
			->execute();
		$db->newInsertQueryBuilder()
			->insert( static::TABLE )
			->row( [
				'wal_instance' => $instance->getId(),
				'wal_instance_path' => $instance->getPath(),
				'wal_level' => $level
			] )
			->caller( __METHOD__ )
			->execute();
		$db->endAtomic( __METHOD__ );
	}

	/**
	 * @param InstanceEntity $instance
	 * @return void
	 */
	public function removeAccessLevelForInstance( InstanceEntity $instance ): void {
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db ) {
			return;
		}
		$db->newDeleteQueryBuilder()
			->delete( static::TABLE )
			->where( [ 'wal_instance' => $instance->getId() ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param array $levels
	 * @return array
	 */
	public function getInstancePathsForLevels( array $levels ): array {
		$levels = array_values( array_unique( $levels ) );
		foreach ( $levels as $level ) {
			$this->assertValidAccessLevel( $level );
		}

		if ( empty( $levels ) ) {
			return [];
		}

		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db ) {
			return [];
		}
		$res = $db->newSelectQueryBuilder()
			->select( 'wal_instance_path' )
			->from( static::TABLE )
			->where( [ 'wal_level' => $levels ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$paths = [];
		foreach ( $res as $row ) {
			$paths[] = $row->wal_instance_path;
		}

		return array_values( array_unique( $paths ) );
	}

	/**
	 * @param string $level
	 * @return void
	 */
	private function assertValidAccessLevel( string $level ): void {
		if ( !in_array( $level, GroupAccessStore::ACCESS_LEVELS ) ) {
			throw new InvalidArgumentException( "Invalid wiki access level: $level" );
		}
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string
	 */
	private function getLegacyAccessLevel( InstanceEntity $instance ): string {
		$level = $instance->getConfig()['wgWikiFarmInitialAccessLevel'] ?? 'private';
		return in_array( $level, GroupAccessStore::ACCESS_LEVELS ) ? $level : 'private';
	}
}
