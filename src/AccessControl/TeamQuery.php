<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

class TeamQuery {

	/** @var array */
	protected array $teamUserRoles = [];

	/**
	 * @param IDatabase $db
	 */
	public function __construct(
		protected readonly IDatabase $db
	) {
	}

	/**
	 * @return array
	 */
	public function getAllTeamGroups(): array {
		if ( MW_ENTRY_POINT === 'cli' && !$this->db->tableExists( 'wiki_teams', __METHOD__ ) ) {
			return [];
		}

		$teams = $this->db->newSelectQueryBuilder()
			->select( 'wt_name' )
			->from( 'wiki_teams' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$res = [];
		foreach ( $teams as $team ) {
			$res[$team->wt_name] = $this->getTeamGroupPrefix() . $team->wt_name;
		}
		return $res;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function hasTeam( string $name ): bool {
		return $this->db->newSelectQueryBuilder()
			->select( 'wt_id' )
			->from( 'wiki_teams' )
			->where( [ 'wt_name' => $name ] )
			->caller( __METHOD__ )
			->fetchRow() !== false;
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
		if ( !isset( $this->teamUserRoles[$user->getId()] ) ) {
			$this->teamUserRoles[$user->getId()] = [];
		}
		if ( isset( $this->teamUserRoles[$user->getId()][$instanceEntity->getId()] ) ) {
			return $this->teamUserRoles[$user->getId()][$instanceEntity->getId()];
		}
		// Get all team group names
		$teamGroups = $this->getAllTeamGroups();
		if ( empty( $teamGroups ) ) {
			$this->teamUserRoles[$user->getId()][$instanceEntity->getId()] = [];
			return [];
		}
		// Get team groups user is in
		$userGroups = $this->db->newSelectQueryBuilder()
			->select( 'ug_group' )
			->from( 'user_groups', 'ug' )
			->where( [
				'ug_user' => $user->getId(),
				'ug_group' => array_values( $teamGroups )
			] )
			->caller( __METHOD__ )
			->fetchResultSet();
		if ( $userGroups->numRows() === 0 ) {
			$this->teamUserRoles[$user->getId()][$instanceEntity->getId()] = [];
			return [];
		}
		// Get teams user is in
		$teams = [];
		$teamGroupsFlipped = array_flip( $teamGroups );
		foreach ( $userGroups as $row ) {
			if ( isset( $teamGroupsFlipped[$row->ug_group] ) ) {
				$teams[] = $teamGroupsFlipped[$row->ug_group];
			}
		}
		if ( empty( $teams ) ) {
			$this->teamUserRoles[$user->getId()][$instanceEntity->getId()] = [];
			return [];
		}
		// Get roles for teams
		$res = $this->db->newSelectQueryBuilder()
			->select( [ 'wtr_role' ] )
			->from( 'wiki_team_roles', 'wtr' )
			->join( 'wiki_teams', 'wt', [ 'wt.wt_id = wtr.wtr_team' ] )
			->where( [
				'wtr_instance' => [ $instanceEntity->getId(), null ],
				'wt_name' => $teams
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$roles = [];
		foreach ( $res as $row ) {
			if ( !isset( GroupAccessStore::ROLES[$row->wtr_role] ) ) {
				throw new RuntimeException( 'Invalid role' );
			}
			$roles[] = $row->wtr_role;
		}
		$this->teamUserRoles[$user->getId()][$instanceEntity->getId()] = $roles;
		return $roles;
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
			->select( [ 'wt_name', 'wtr_role', 'wtr_instance' ] )
			->from( 'wiki_team_roles', 'wtr' )
			->join( 'wiki_teams', 'wt', [ 'wt.wt_id = wtr.wtr_team' ] )
			->where( [
				'wtr_instance' => [ $instanceEntity->getId(), null ],
			] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$roles = [];
		foreach ( $res as $row ) {
			$roles[] = [
				'team' => $row->wt_name,
				'role' => $row->wtr_role,
				'isGlobal' => $row->wtr_instance === null,
			];
		}
		return $roles ?? [];
	}

	/**
	 * @param string $teamName
	 * @return string
	 */
	public function getTeamGroupName( string $teamName ): string {
		return $this->getTeamGroupPrefix() . "$teamName";
	}

	/**
	 * @return string
	 */
	protected function getTeamGroupPrefix(): string {
		return 'team-';
	}

	/**
	 * @param string $teamName
	 * @return Team|null
	 */
	protected function fetchTeam( string $teamName ) {
		$res = $this->db->newSelectQueryBuilder()
			->from( 'wiki_teams' )
			->select( '*' )
			->where( [ 'wt_name' => $teamName ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( !$res ) {
			return null;
		}

		return new Team(
			(int)$res->wt_id,
			$res->wt_name,
			$res->wt_description ?? ''
		);
	}

	/**
	 * @param string $name
	 * @return Team
	 */
	public function getTeam( string $name ): Team {
		$team = $this->fetchTeam( $name );
		if ( !$team ) {
			throw new RuntimeException( 'Team not found' );
		}
		$team->setMemberCount( $this->getMemberCount( $team ) );
		return $team;
	}

	/**
	 * @return array
	 */
	public function getAllTeams(): array {
		$res = $this->db->newSelectQueryBuilder()
			->from( 'wiki_teams' )
			->select( '*' )
			->caller( __METHOD__ )
			->fetchResultSet();

		$teams = [];
		foreach ( $res as $row ) {
			$team = new Team(
				(int)$row->wt_id,
				$row->wt_name,
				$row->wt_description ?? ''
			);
			$team->setMemberCount( $this->getMemberCount( $team ) );
			$teams[] = $team;
		}
		return $teams;
	}

	/**
	 * @param Team $team
	 * @return int
	 */
	public function getMemberCount( Team $team ): int {
		$teamGroup = $this->getTeamGroupName( $team->getName() );
		return $this->db->newSelectQueryBuilder()
			->from( 'user_groups', 'ug' )
			->select( 'ug_user' )
			->where( [ 'ug_group' => $teamGroup ] )
			->caller( __METHOD__ )
			->fetchRowCount();
	}
}
