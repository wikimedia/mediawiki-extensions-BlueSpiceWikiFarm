<?php

namespace BlueSpice\WikiFarm\AccessControl;

use Wikimedia\Rdbms\ILoadBalancer;

class GroupListStore {

	/**
	 * @param ILoadBalancer $lb
	 */
	public function __construct(
		private readonly ILoadBalancer $lb
	) {
	}

	/**
	 * @param string $group
	 * @return void
	 */
	public function addGroup( string $group ): void {
		if ( $this->hasGroup( $group ) ) {
			return;
		}
		$this->lb->getConnection( DB_PRIMARY )->newInsertQueryBuilder()
			->insert( 'wikifarm_groups' )
			->row( [
				'wfg_name' => $group,
				'wfg_index' => mb_strtolower( $group ),
			] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @return array
	 */
	public function getGroups(): array {
		$res = $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->from( 'wikifarm_groups' )
			->select( [ 'wfg_name' ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$groups = [];
		foreach ( $res as $row ) {
			$groups[] = $row->wfg_name;
		}
		return $groups;
	}

	/**
	 * @param string $group
	 * @return void
	 */
	public function removeGroup( string $group ): void {
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->delete( 'wikifarm_groups' )
			->where( [ 'wfg_name' => $group ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param array $groups
	 * @return void
	 */
	public function setGroups( array $groups ): void {
		$this->lb->getConnection( DB_PRIMARY )->newDeleteQueryBuilder()
			->delete( 'wikifarm_groups' )
			->where( '1=1' )
			->caller( __METHOD__ )
			->execute();
		$groupRows = [];
		foreach ( $groups as $group ) {
			$groupRows[] = [
				'wfg_name' => $group,
				'wfg_index' => mb_strtolower( $group ),
			];
		}
		if ( empty( $groupRows ) ) {
			return;
		}
		$this->lb->getConnection( DB_PRIMARY )->newInsertQueryBuilder()
			->insert( 'wikifarm_groups' )
			->rows( $groupRows )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string $group
	 * @return bool
	 */
	private function hasGroup( string $group ): bool {
		return $this->lb->getConnection( DB_REPLICA )->newSelectQueryBuilder()
			->from( 'wikifarm_groups' )
			->select( [ 'wfg_name' ] )
			->where( [ 'wfg_name' => $group ] )
			->caller( __METHOD__ )
			->fetchField() !== false;
	}
}
