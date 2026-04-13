<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\IDatabase;

class GroupRoleQuery {

	private HashBagOStuff $opCache;

	/**
	 * @param IDatabase $db
	 */
	public function __construct(
		protected readonly IDatabase $db
	) {
		$this->opCache = new HashBagOStuff();
	}

	/**
	 * Get roles a user is assigned through their group memberships
	 *
	 * @param UserIdentity $user
	 * @param InstanceEntity $instanceEntity
	 * @return array
	 */
	public function getUserRolesForInstance( UserIdentity $user, InstanceEntity $instanceEntity ): array {
		if ( MW_ENTRY_POINT === 'cli' && !$this->db->tableExists( 'wiki_team_roles', __METHOD__ ) ) {
			return [];
		}
		if ( !$user->isRegistered() ) {
			return [];
		}

		$cacheKey = $this->opCache->makeKey( 'group-roles-per-instance', $user->getName(), $instanceEntity->getId() );
		if ( $this->opCache->hasKey( $cacheKey ) ) {
			return $this->opCache->get( $cacheKey );
		}

		// Find groups the user is in that have role assignments for this instance
		$res = $this->db->newSelectQueryBuilder()
			->select( [ 'wtr_role' ] )
			->from( 'user_groups', 'ug' )
			->join( 'wiki_team_roles', 'wtr', [ 'ug_group = wtr_team' ] )
			->where( [
				'ug_user' => $user->getId(),
				$this->db->makeList( [
					'wtr_instance IS NULL',
					'wtr_instance' => $instanceEntity->getId()
				], LIST_OR )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$roles = [];
		foreach ( $res as $row ) {
			if ( isset( IAccessStore::ROLES[$row->wtr_role] ) ) {
				$roles[] = $row->wtr_role;
			}
		}

		$roles = array_unique( $roles );
		$this->opCache->set( $cacheKey, $roles );
		return $roles;
	}

	/**
	 * Get roles assigned to groups for a given instance
	 *
	 * @param InstanceEntity $instanceEntity
	 * @return array
	 */
	public function getGroupRoles( InstanceEntity $instanceEntity ): array {
		if ( MW_ENTRY_POINT === 'cli' && !$this->db->tableExists( 'wiki_team_roles', __METHOD__ ) ) {
			return [];
		}
		$res = $this->db->newSelectQueryBuilder()
			->select( [ 'wtr_team', 'wtr_role', 'wtr_instance' ] )
			->from( 'wiki_team_roles', 'wtr' )
			->where( [
				$this->db->makeList( [
					'wtr_instance IS NULL',
					'wtr_instance' => $instanceEntity->getId()
				], LIST_OR )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$roles = [];
		foreach ( $res as $row ) {
			$roles[] = [
				'group' => $row->wtr_team,
				'role' => $row->wtr_role,
				'isGlobal' => $row->wtr_instance === null,
			];
		}
		return $roles;
	}

	/**
	 * Get all groups that have any role assignment
	 *
	 * @return array
	 */
	public function getAllGroupsWithRoles(): array {
		if ( MW_ENTRY_POINT === 'cli' && !$this->db->tableExists( 'wiki_team_roles', __METHOD__ ) ) {
			return [];
		}
		$res = $this->db->newSelectQueryBuilder()
			->field( 'DISTINCT( wtr_team )', 'group_name' )
			->from( 'wiki_team_roles' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$groups = [];
		foreach ( $res as $row ) {
			$groups[] = $row->group_name;
		}
		return $groups;
	}

	/**
	 * Get groups that have any of the given roles on the given instance (or globally)
	 *
	 * @param array $roles
	 * @param InstanceEntity $onInstance
	 * @return array Map of group name => group name
	 */
	public function getGroupsWithRoles( array $roles, InstanceEntity $onInstance ): array {
		if ( !$this->db->tableExists( 'wiki_team_roles', __METHOD__ ) ) {
			return [];
		}

		$res = $this->db->newSelectQueryBuilder()
			->select( 'wtr_team' )
			->from( 'wiki_team_roles', 'wtr' )
			->where( [
				'wtr_role' => $roles,
				$this->db->makeList( [
					'wtr_instance IS NULL',
					'wtr_instance' => $onInstance->getId()
				], LIST_OR )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$groups = [];
		foreach ( $res as $row ) {
			$groups[$row->wtr_team] = $row->wtr_team;
		}

		return $groups;
	}

	/**
	 * Get all groups from wiki_team_roles that are NOT in the given set
	 *
	 * @param array $groups Groups to exclude
	 * @return array Map of group name => group name
	 */
	public function invertGroups( array $groups ): array {
		if ( empty( $groups ) ) {
			return [];
		}
		$res = $this->db->newSelectQueryBuilder()
			->select( 'DISTINCT( wtr_team ) team' )
			->from( 'wiki_team_roles', 'wtr' )
			->conds( [
				'wtr_team NOT IN (' . $this->db->makeList( array_values( $groups ) ) . ')',
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$inverted = [];
		foreach ( $res as $row ) {
			$inverted[$row->team] = $row->team;
		}

		return $inverted;
	}

	/**
	 * @param UserIdentity $user
	 * @param InstanceEntity $instanceEntity
	 * @return void
	 */
	public function clearCache( UserIdentity $user, InstanceEntity $instanceEntity ): void {
		$cacheKey = $this->opCache->makeKey( 'group-roles-per-instance', $user->getName(), $instanceEntity->getId() );
		$this->opCache->delete( $cacheKey );
	}
}
