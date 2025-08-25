<?php

namespace BlueSpice\WikiFarm\Data\AccessAssignments;

use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\InstanceEntity;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\ILoadBalancer;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 *
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param ILoadBalancer $lb
	 * @param TeamManager $teamManager
	 * @param InstanceEntity $instanceEntity
	 */
	public function __construct(
		private readonly InstanceGroupCreator $instanceGroupCreator,
		private readonly ILoadBalancer $lb,
		private readonly TeamManager $teamManager,
		private readonly InstanceEntity $instanceEntity
	) {
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$assignments = $this->getGlobalAssignments();
		$localUserAssignments = $this->getLocalUserAssignments();
		$teamAssignments = $this->getTeamAssignments( $this->teamManager->getTeamRoles( $this->instanceEntity ) );

		$assignments = array_merge( $assignments, $localUserAssignments, $teamAssignments );

		$records = [];
		foreach ( $assignments as $assignment ) {
			$records[] = new Record( (object)[
				Record::ENTITY_TYPE => $assignment['type'],
				Record::ENTITY_KEY => $assignment['key'],
				Record::ROLE => $assignment['role'],
				Record::IS_GLOBAL_ASSIGNMENT => $assignment['is_global'],
			] );
		}
		return $records;
	}

	/**
	 * @return array
	 */
	private function getGlobalAssignments() {
		$groups = $this->instanceGroupCreator->getGlobalGroups();
		return $this->getAssignmentsForUserGroups( $groups, true );
	}

	/**
	 * @return array
	 */
	private function getLocalUserAssignments(): array {
		$groups = $this->instanceGroupCreator->getGroupsAndRolesForInstancePath( $this->instanceEntity->getPath() );
		return $this->getAssignmentsForUserGroups( $groups, false );
	}

	/**
	 * @param array $groups
	 * @param bool $isGlobal
	 * @return array
	 */
	private function getAssignmentsForUserGroups( array $groups, bool $isGlobal = false ) {
		$db = $this->lb->getConnection( DB_REPLICA );
		$res = $db->newSelectQueryBuilder()
			->from( 'user_groups', 'ug' )
			->join( 'user', 'u', [ 'ug.ug_user = u.user_id' ] )
			->select( [ 'user_name', 'ug_group' ] )
			->where( [ 'ug_group' => array_keys( $groups ) ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$assignments = [];
		foreach ( $res as $row ) {
			$role = $this->instanceGroupCreator->getRoleFromGroupName( $row->ug_group );
			if ( !$role ) {
				continue;
			}
			$assignments[] = [
				'type' => 'user',
				'key' => $row->user_name,
				'role' => $role,
				'is_global' => $isGlobal,
			];
		}

		return $assignments;
	}

	/**
	 * @param array $teamRoles
	 * @return array
	 */
	private function getTeamAssignments( array $teamRoles ): array {
		$assignments = [];
		foreach ( $teamRoles as $data ) {
			$assignments[] = [
				'type' => 'team',
				'key' => $data['team'],
				'role' => $data['role'],
				'is_global' => $data['isGlobal'],
			];
		}
		return $assignments;
	}

}
