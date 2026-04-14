<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\Rdbms\IDatabase;

class TeamQuery {

	/** @var array */
	protected array $teamUserRoles = [];

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
	 * @return array
	 */
	public function getAllTeamGroups(): array {
		if ( MW_ENTRY_POINT === 'cli' && !$this->db->tableExists( 'wiki_team_roles', __METHOD__ ) ) {
			return [];
		}

		// Get all teams this user is part of for this instances
		$teamsRes = $this->db->newSelectQueryBuilder()
			->field( 'DISTINCT( ug_group )', 'group' )
			->from( 'user_groups', 'ug' )
			->conds( [
				'ug_group LIKE ' . $this->db->addQuotes( $this->getTeamPrefix() . '%' )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $teamsRes->numRows() === 0 ) {
			return [];
		}
		$teamGroups = [];
		foreach ( $teamsRes as $row ) {
			$teamGroups[] = $row->group;
		}
		return $teamGroups;
	}

	/**
	 * Get roles user is assigned though their team memberships
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

		$cacheKey = $this->opCache->makeKey( 'roles-per-instance', $user->getName(), $instanceEntity->getId() );
		if ( $this->opCache->hasKey( $cacheKey ) ) {
			return $this->opCache->get( $cacheKey );
		}

		// Get all teams this user is part of for this instances
		$teamsRes = $this->db->newSelectQueryBuilder()
			->select( 'ug_group' )
			->select( 'wtr_role' )
			->from( 'user_groups', 'ug' )
			->from( 'wiki_team_roles', 'wtr' )
			->conds( [
				'ug_user' => $user->getId(),
				$this->db->makeList( [
					'wtr_instance IS NULL',
					'wtr_instance' => $instanceEntity->getPath()
				], LIST_OR )
			] )
			->join( 'wiki_team_roles', 'wtr', $this->getPrefixedJoinCondition() )
			->caller( __METHOD__ )
			->fetchResultSet();

		if ( $teamsRes->numRows() === 0 ) {
			return [];
		}
		$roles = [];
		foreach ( $teamsRes as $row ) {
			if ( !isset( IAccessStore::ROLES[$row->wtr_role] ) ) {
				continue;
			}
			$roles[] = $row->wtr_role;
		}

		$this->opCache->set( $cacheKey, array_unique( $roles ) );
		return $this->opCache->get( $cacheKey );
	}

	/**
	 * @param array $roles
	 * @param InstanceEntity $onInstance
	 * @return array Key is team name, value is group name
	 */
	public function getTeamsWithRoles( array $roles, InstanceEntity $onInstance ) {
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

		$teams = [];
		foreach ( $res as $row ) {
			$teams[$row->wtr_team] = $this->getTeamGroupName( $row->wtr_team );
		}

		return $teams;
	}

	/**
	 * Get all teams other than teams passed
	 *
	 * @param array $teams to filter out
	 * @return array
	 */
	public function invertTeams( array $teams ): array {
		if ( empty( $teams ) ) {
			return [];
		}
		$invRes = $this->db->newSelectQueryBuilder()
			->select( 'DISTINCT( wtr_team ) team' )
			->from( 'wiki_team_roles', 'wtr' )
			->conds( [
				'wtr_team NOT IN (' . $this->db->makeList( array_values( $teams ) ) . ')',
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$inverted = [];
		foreach ( $invRes as $row ) {
			$inverted[$row->team] = $this->getTeamGroupName( $row->team );
		}

		return $inverted;
	}

	/**
	 * Get roles assigned to each team for given instance
	 *
	 * @param InstanceEntity $instanceEntity
	 * @return array
	 */
	public function getTeamRoles( InstanceEntity $instanceEntity ): array {
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
			if ( is_numeric( $row->wtr_team ) ) {
				// Legacy - team ID
				continue;
			}
			$roles[] = [
				'team' => $row->wtr_team,
				'role' => $row->wtr_role,
				'isGlobal' => $row->wtr_instance === null,
			];
		}
		return $roles ?? [];
	}

	/**
	 * @return string
	 */
	public function getTeamPrefix(): string {
		return 'team-';
	}

	/**
	 * @param string $teamName
	 * @return string
	 */
	public function getTeamGroupName( string $teamName ): string {
		return $this->getTeamPrefix() . $teamName;
	}

	/**
	 * @param UserIdentity $user
	 * @param InstanceEntity $instanceEntity
	 * @return void
	 */
	public function clearCache( UserIdentity $user, InstanceEntity $instanceEntity ): void {
		$cacheKey = $this->opCache->makeKey( 'roles-per-instance', $user->getName(), $instanceEntity->getId() );
		$this->opCache->delete( $cacheKey );
	}

	/**
	 * @return string[]
	 */
	private function getPrefixedJoinCondition(): array {
		$teamPrefix = $this->getTeamPrefix();
		$groupJoinName = 'CONCAT(' . $this->db->addQuotes( $teamPrefix ) . ', wtr_team)';
		return [ "ug_group = $groupJoinName" ];
	}

}
