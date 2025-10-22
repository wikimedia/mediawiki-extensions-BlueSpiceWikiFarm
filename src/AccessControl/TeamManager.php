<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentity;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

class TeamManager extends TeamQuery {

	/**
	 * @param IDatabase $db
	 * @param UserGroupManager $userGroupManager
	 * @param UserFactory $userFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IDatabase $db,
		private readonly UserGroupManager $userGroupManager,
		private readonly UserFactory $userFactory,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( $db );
	}

	/**
	 * @param string $name
	 * @param string $description
	 * @param Authority $actor
	 * @return Team
	 */
	public function createTeam( string $name, string $description, Authority $actor ): Team {
		$exists = $this->db->newSelectQueryBuilder()
			->select( 'wt_id' )
			->from( 'wiki_teams' )
			->where( [ 'wt_name' => $name ] )
			->caller( __METHOD__ )
			->fetchRow();
		if ( $exists ) {
			throw new RuntimeException( 'Team already exists' );
		}
		$this->db->newInsertQueryBuilder()
			->insert( 'wiki_teams' )
			->row( [
				'wt_name' => $name,
				'wt_description' => $description,
				'wt_creator' => $actor->getUser()->getId(),
				'wt_created' => $this->db->timestamp()
			] )
			->caller( __METHOD__ )
			->execute();
		$id = $this->db->insertId();
		if ( !is_int( $id ) || $id === 0 ) {
			$this->logger->error( 'Failed to create team', [ 'name' => $name ] );
			throw new RuntimeException( 'Failed to create team' );
		}
		$this->logger->info( 'Created team', [ 'name' => $name ] );
		$this->logToSpecialLog( 'create', $actor, [ '4::teamName' => $name ] );
		return new Team( $id, $name, $description );
	}

	/**
	 * @param Team $team
	 * @param Authority $actor
	 * @return void
	 */
	public function deleteTeam( Team $team, Authority $actor ) {
		$this->db->startAtomic( __METHOD__ );
		// Un-assign all users from the team
		$group = $this->getTeamGroupName( $team->getName() );
		// Remove all team roles
		$this->db->newDeleteQueryBuilder()
			->delete( 'wiki_team_roles' )
			->where( [
				'wtr_team' => $team->getId(),
			] )
			->caller( __METHOD__ )
			->execute();
		$this->db->newDeleteQueryBuilder()
			->delete( 'user_groups' )
			->where( [ 'ug_group' => $group ] )
			->caller( __METHOD__ )
			->execute();
		// Team group assignments
		$this->db->newDeleteQueryBuilder()
			->delete( 'user_groups' )
			->where( [ 'ug_group ' . $this->db->buildLike( $group, $this->db->anyString() ) ] )
			->caller( __METHOD__ )
			->execute();
		// Delete the team
		$this->db->newDeleteQueryBuilder()
			->delete( 'wiki_teams' )
			->where( [ 'wt_id' => $team->getId() ] )
			->caller( __METHOD__ )
			->execute();
		$this->db->endAtomic( __METHOD__ );
		$this->logToSpecialLog( 'delete', $actor, [ '4::teamName' => $team->getName() ] );
		$this->logger->info( 'Deleted team', [ 'name' => $team->getName() ] );
	}

	/**
	 * Only one role can be assigned to a team and instance at a time
	 *
	 * @param string $role
	 * @param Team $team
	 * @param InstanceEntity|null $instanceEntity If null, team role is assigned globally
	 * @param Authority $actor
	 * @return void
	 */
	public function assignRoleToTeam( string $role, Team $team, ?InstanceEntity $instanceEntity, Authority $actor ) {
		if ( !isset( GroupAccessStore::ROLES[$role] ) ) {
			throw new RuntimeException( 'Invalid role' );
		}
		$this->db->startAtomic( __METHOD__ );
		$this->removeAllRoles( $team, $instanceEntity, $actor );
		$this->db->newInsertQueryBuilder()
			->insert( 'wiki_team_roles' )
			->row( [
				'wtr_team' => $team->getId(),
				'wtr_instance' => $instanceEntity?->getId(),
				'wtr_role' => $role
			] )
			->caller( __METHOD__ )
			->execute();
		$this->db->endAtomic( __METHOD__ );

		$this->logToSpecialLog( 'assign-role', $actor,
			[ '4::teamName' => $team->getName(), '5::role' => $role, ]
		);
		$this->logger->info( 'Assigned role "{role}" to team "{team}" on instance "{instance}"', [
			'role' => $role,
			'team' => $team->getName(), 'instance' => $instanceEntity ? $instanceEntity->getPath() : '_global'
		] );
		$this->teamUserRoles = [];
	}

	/**
	 * @param Team $team
	 * @param InstanceEntity|null $instanceEntity If  null, team role is removed globally
	 * @param Authority $actor
	 * @param bool $shouldLog
	 * @return void
	 */
	public function removeAllRoles(
		Team $team, ?InstanceEntity $instanceEntity, Authority $actor, bool $shouldLog = false
	) {
		$this->db->newDeleteQueryBuilder()
			->delete( 'wiki_team_roles' )
			->where( [
				'wtr_team' => $team->getId(),
				'wtr_instance' => $instanceEntity?->getId(),
			] )
			->caller( __METHOD__ )
			->execute();

		if ( $shouldLog ) {
			$this->logToSpecialLog( 'remove-all-roles', $actor,
				[ '4::teamName' => $team->getName() ]
			);
		}

		$this->logger->info( 'Removed all roles from team "{team}" on instance "{instance}"', [
			'team' => $team->getName(), 'instance' => $instanceEntity ? $instanceEntity->getPath() : '_global'
		] );
		$this->teamUserRoles = [];
	}

	/**
	 * @param UserIdentity $user
	 * @param Team $team
	 * @param Authority $actor
	 * @param string|null $expiry
	 * @return void
	 */
	public function assignUserToTeam( UserIdentity $user, Team $team, Authority $actor, ?string $expiry = null ) {
		$teamGroup = $this->getTeamGroupName( $team->getName() );
		if ( !$this->userGroupManager->addUserToGroup( $user, $teamGroup, $expiry, $expiry !== null ) ) {
			$this->logger->error( 'Failed to assign user to team', [ 'user' => $user->getName(), 'team' => $teamGroup ] );
			throw new RuntimeException( 'Failed to assign user to team' );
		}
		$this->logToSpecialLog( 'assign-user', $actor, [ $team->getName(), $user->getName() ] );
		$this->logger->info( 'Assigned user to team', [ 'user' => $user->getName(), 'team' => $teamGroup ] );
	}

	/**
	 * @param UserIdentity $user
	 * @param Team $team
	 * @param Authority $actor
	 * @return void
	 */
	public function removeUserFromTeam( UserIdentity $user, Team $team, Authority $actor ) {
		$teamGroup = $this->getTeamGroupName( $team->getName() );
		if ( !$this->userGroupManager->removeUserFromGroup( $user, $teamGroup ) ) {
			$this->logger->error( 'Failed to remove user from team', [ 'user' => $user->getName(), 'team' => $teamGroup ] );
			throw new RuntimeException( 'Failed to remove user from team' );
		}
		$this->logToSpecialLog( 'un-assign-user', $actor, [ $team->getName(), $user->getName() ] );
		$this->logger->info( 'Removed user from team', [ 'user' => $user->getName(), 'team' => $teamGroup ] );
	}

	/**
	 * @param Team $team
	 * @return array
	 */
	public function getMembers( Team $team ): array {
		$teamGroup = $this->getTeamGroupName( $team->getName() );
		$res = $this->db->newSelectQueryBuilder()
			->from( 'user_groups', 'ug' )
			->join( 'user', 'u', [ 'user_id = ug_user' ] )
			->select( [ 'u.*', 'ug_expiry' ] )
			->where( [ 'ug_group' => $teamGroup ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$members = [];
		foreach ( $res as $row ) {
			$members[$row->user_name] = [
				'user' => $this->userFactory->newFromRow( $row ),
				'expiration' => $row->ug_expiry
			];
		}
		return $members;
	}

	/**
	 * @param string $action
	 * @param Authority $actor
	 * @param array $params
	 * @return void
	 */
	private function logToSpecialLog( string $action, Authority $actor, array $params ) {
		$logEntry = new ManualLogEntry( 'ext-wikifarm-teams', $action );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiTeams' ) );
		$logEntry->setPerformer( $actor->getUser() );
		$logEntry->setParameters( $params );
		$id = $logEntry->insert();
		$logEntry->publish( $id );
	}
}
