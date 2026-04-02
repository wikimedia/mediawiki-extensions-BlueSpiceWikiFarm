<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use ManualLogEntry;
use MediaWiki\Permissions\Authority;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

class TeamManager extends TeamQuery {

	/**
	 * @param IDatabase $db
	 * @param UserFactory $userFactory
	 * @param LoggerInterface $logger
	 */
	public function __construct(
		IDatabase $db,
		private readonly UserFactory $userFactory,
		private readonly LoggerInterface $logger
	) {
		parent::__construct( $db );
	}

	/**
	 * Only one role can be assigned to a team and instance at a time
	 *
	 * @param string $role
	 * @param string $teamName
	 * @param InstanceEntity|null $instanceEntity If null, team role is assigned globally
	 * @param Authority $actor
	 * @return void
	 */
	public function assignRoleToTeam(
		string $role, string $teamName, ?InstanceEntity $instanceEntity, Authority $actor
	): void {
		if ( !isset( IAccessStore::ROLES[$role] ) ) {
			throw new RuntimeException( 'Invalid role' );
		}
		$this->db->startAtomic( __METHOD__ );
		$this->removeAllRoles( $teamName, $instanceEntity, $actor );
		$this->db->newInsertQueryBuilder()
			->insert( 'wiki_team_roles' )
			->row( [
				'wtr_team' => $teamName,
				'wtr_instance' => $instanceEntity?->getId(),
				'wtr_role' => $role
			] )
			->caller( __METHOD__ )
			->execute();
		$this->db->endAtomic( __METHOD__ );

		$this->logToSpecialLog( 'assign-role', $actor,
			[ '4::teamName' => $teamName, '5::role' => $role, ]
		);
		$this->logger->info( 'Assigned role "{role}" to team "{team}" on instance "{instance}"', [
			'role' => $role,
			'team' => $teamName, 'instance' => $instanceEntity ? $instanceEntity->getPath() : '_global'
		] );
	}

	/**
	 * @param string $teamName
	 * @param InstanceEntity|null $instanceEntity If  null, team role is removed globally
	 * @param Authority $actor
	 * @param bool $shouldLog
	 * @return void
	 */
	public function removeAllRoles(
		string $teamName, ?InstanceEntity $instanceEntity, Authority $actor, bool $shouldLog = false
	): void {
		$this->db->newDeleteQueryBuilder()
			->delete( 'wiki_team_roles' )
			->where( [
				'wtr_team' => $teamName,
				'wtr_instance' => $instanceEntity?->getId(),
			] )
			->caller( __METHOD__ )
			->execute();

		if ( $shouldLog ) {
			$this->logToSpecialLog( 'remove-all-roles', $actor,
				[ '4::teamName' => $teamName ]
			);
		}
		$this->logger->info( 'Removed all roles from team "{team}" on instance "{instance}"', [
			'team' => $teamName, 'instance' => $instanceEntity ? $instanceEntity->getPath() : '_global'
		] );
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
	protected function logToSpecialLog( string $action, Authority $actor, array $params ): void {
		$logEntry = new ManualLogEntry( 'ext-wikifarm-teams', $action );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'WikiTeams' ) );
		$logEntry->setPerformer( $actor->getUser() );
		$logEntry->setParameters( $params );
		$id = $logEntry->insert();
		$logEntry->publish( $id );
	}
}
