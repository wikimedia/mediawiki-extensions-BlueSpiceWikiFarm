<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use Wikimedia\Rdbms\ILoadBalancer;

class WikiAccessLookup {

	private const TABLE = 'wiki_access_levels';

	/**
	 * @param ILoadBalancer $lb
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly ILoadBalancer $lb,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * Access levels that can be set for a wiki (farm config `accessLevels`)
	 *
	 * @return string[]
	 */
	public function getAccessLevels(): array {
		$levels = $this->farmConfig->get( 'accessLevels' );
		if ( !is_array( $levels ) ) {
			return GroupAccessStore::ACCESS_LEVELS;
		}
		return array_values( array_intersect( GroupAccessStore::ACCESS_LEVELS, $levels ) );
	}

	/**
	 * Whether the level can be set for a wiki (farm config `accessLevels`)
	 *
	 * @param string $level
	 * @return bool
	 */
	public function isValidAccessLevel( string $level ): bool {
		return in_array( $level, $this->getAccessLevels(), true );
	}

	/**
	 * Whether the level is known at all, regardless of the farm config.
	 * Wikis may still have a level that is not configured (anymore)
	 *
	 * @param string $level
	 * @return bool
	 */
	private function isKnownAccessLevel( string $level ): bool {
		return in_array( $level, GroupAccessStore::ACCESS_LEVELS, true );
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

		if ( !$row || !$this->isKnownAccessLevel( $row->wal_level ) ) {
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
	 * @param InstanceEntity $source
	 * @param InstanceEntity $target
	 * @return void
	 */
	public function cloneAccessLevel( InstanceEntity $source, InstanceEntity $target ): void {
		$db = $this->lb->getConnection( DB_PRIMARY );
		if ( !$db ) {
			return;
		}
		$level = $this->getAccessLevelForInstance( $source );
		$row = $db->newSelectQueryBuilder()
			->table( static::TABLE )
			->fields( 'wal_level' )
			->where( [
				'wal_instance' => $target->getId(),
				'wal_instance_path' => $target->getPath(),
			] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $row ) {
			$db->newUpdateQueryBuilder()
				->update( static::TABLE )
				->set( [
					'wal_level' => $level
				] )
				->where( [
					'wal_instance' => $target->getId(),
					'wal_instance_path' => $target->getPath(),
				] )
				->caller( __METHOD__ )
				->execute();
		} else {
			$db->newInsertQueryBuilder()
				->insert( static::TABLE )
				->row( [
					'wal_instance' => $target->getId(),
					'wal_instance_path' => $target->getPath(),
					'wal_level' => $level
				] )
				->caller( __METHOD__ )
				->execute();
		}
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
			$this->assertValidAccessLevel( $level, false );
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
	 * @param bool $configuredOnly Whether the level must be configured, or only be known
	 * @return void
	 * @throws InvalidArgumentException
	 */
	private function assertValidAccessLevel( string $level, bool $configuredOnly = true ): void {
		$isValid = $configuredOnly ? $this->isValidAccessLevel( $level ) : $this->isKnownAccessLevel( $level );
		if ( !$isValid ) {
			throw new InvalidArgumentException( "Invalid access level: $level" );
		}
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string
	 */
	private function getLegacyAccessLevel( InstanceEntity $instance ): string {
		$level = $instance->getConfig()['wgWikiFarmInitialAccessLevel'] ?? 'private';
		return $this->isKnownAccessLevel( $level ) ? $level : 'private';
	}
}
