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

class GroupRoleManager extends GroupRoleQuery {

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
	 * Assign a role to a group on an instance (or globally if instance is null)
	 * Only one role can be assigned to a group per instance at a time
	 *
	 * @param string $role
	 * @param string $groupName
	 * @param InstanceEntity|null $instanceEntity If null, role is assigned globally
	 * @param Authority $actor
	 * @return void
	 */
	public function assignRoleToGroup(
		string $role, string $groupName, ?InstanceEntity $instanceEntity, Authority $actor
	): void {
		if ( !isset( IAccessStore::ROLES[$role] ) ) {
			throw new RuntimeException( 'Invalid role' );
		}
		$this->db->startAtomic( __METHOD__ );
		$this->removeGroupRoles( $groupName, $instanceEntity, $actor );
		$this->db->newInsertQueryBuilder()
			->insert( 'wiki_team_roles' )
			->row( [
				'wtr_team' => $groupName,
				'wtr_instance' => $instanceEntity?->getId(),
				'wtr_role' => $role
			] )
			->caller( __METHOD__ )
			->execute();
		$this->db->endAtomic( __METHOD__ );

		$this->logToSpecialLog( 'assign-role', $actor,
			[ '4::groupName' => $groupName, '5::role' => $role ]
		);
		$this->logger->info( 'Assigned role "{role}" to group "{group}" on instance "{instance}"', [
			'role' => $role,
			'group' => $groupName,
			'instance' => $instanceEntity ? $instanceEntity->getPath() : '_global'
		] );
	}

	/**
	 * Remove all role assignments for a group on an instance
	 *
	 * @param string $groupName
	 * @param InstanceEntity|null $instanceEntity If null, removes global assignment
	 * @param Authority $actor
	 * @param bool $shouldLog
	 * @return void
	 */
	public function removeGroupRoles(
		string $groupName, ?InstanceEntity $instanceEntity, Authority $actor, bool $shouldLog = false
	): void {
		$this->db->newDeleteQueryBuilder()
			->delete( 'wiki_team_roles' )
			->where( [
				'wtr_team' => $groupName,
				'wtr_instance' => $instanceEntity?->getId(),
			] )
			->caller( __METHOD__ )
			->execute();

		if ( $shouldLog ) {
			$this->logToSpecialLog( 'remove-all-roles', $actor,
				[ '4::groupName' => $groupName ]
			);
		}
		$this->logger->info( 'Removed all roles from group "{group}" on instance "{instance}"', [
			'group' => $groupName,
			'instance' => $instanceEntity ? $instanceEntity->getPath() : '_global'
		] );
	}

	/**
	 * Delete all role assignments for a group (across all instances)
	 *
	 * @param string $groupName
	 * @param Authority $actor
	 * @return void
	 */
	public function deleteAllRolesForGroup( string $groupName, Authority $actor ): void {
		$this->db->newDeleteQueryBuilder()
			->delete( 'wiki_team_roles' )
			->where( [
				'wtr_team' => $groupName,
			] )
			->caller( __METHOD__ )
			->execute();

		$this->logToSpecialLog( 'delete', $actor, [ '4::groupName' => $groupName ] );
		$this->logger->info( 'Deleted all roles for group "{group}"', [ 'group' => $groupName ] );
	}

	/**
	 * @param string $action
	 * @param Authority $actor
	 * @param array $params
	 * @return void
	 */
	protected function logToSpecialLog( string $action, Authority $actor, array $params ): void {
		$logEntry = new ManualLogEntry( 'ext-wikifarm-access', $action );
		$logEntry->setTarget( SpecialPage::getTitleFor( 'AccessManagement' ) );
		$logEntry->setPerformer( $actor->getUser() );
		$logEntry->setParameters( $params );
		$id = $logEntry->insert();
		$logEntry->publish( $id );
	}
}
