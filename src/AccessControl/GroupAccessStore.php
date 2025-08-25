<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use MediaWiki\User\UserIdentity;

class GroupAccessStore implements IAccessStore {
	public const ROLES = [
		'reader' => [ 'reader' ],
		'editor' => [ 'reader', 'editor' ],
		'maintainer' => [ 'reader', 'editor', 'reviewer', 'admin' ],
		'reviewer' => [ 'reader', 'editor', 'reviewer' ],
	];

	public const ACCESS_LEVELS = [
		'public', 'protected', 'private'
	];

	/** @var array */
	private $userRoles = [];

	/**
	 * @param ManagementDatabaseFactory $databaseFactory
	 * @param InstanceGroupCreator $groupCreator
	 * @param TeamQuery $teamQuery
	 */
	public function __construct(
		private readonly ManagementDatabaseFactory $databaseFactory,
		private readonly InstanceGroupCreator $groupCreator,
		private readonly TeamQuery $teamQuery
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function userHasRoleOnInstance( UserIdentity $user, string $role, InstanceEntity $instance ): bool {
		if ( !isset( $this->userRoles[$user->getId()][$instance->getPath()][$role] ) ) {
			$db = $this->databaseFactory->createSharedUserDatabaseConnection();
			$group = $this->groupCreator->getGroupNameForUserRole( $instance->getPath(), $role );
			$globalGroup = $this->groupCreator->getGroupNameForUserRole( '_global', $role );
			$res = $db->selectRow(
				'user_groups',
				[ 'ug_user' ],
				[
					'ug_user' => $user->getId(),
					'ug_group IN (' . $db->makeList( [ $group, $globalGroup ] ) . ')'
				],
				__METHOD__
			);
			$db->close( __METHOD__ );
			$hasRole = $res !== false;
			if ( !$hasRole ) {
				$hasRole = $this->checkTeams( $user, $role, $instance );
			}

			$this->userRoles[$user->getId()] = $this->userRoles[$user->getId()] ?? [];
			$this->userRoles[$user->getId()][$instance->getPath()] =
				$this->userRoles[$user->getId()][$instance->getPath()] ?? [];
			$this->userRoles[$user->getId()][$instance->getPath()][$role] = $hasRole;
		}

		return $this->userRoles[$user->getId()][$instance->getPath()][$role];
	}

	/**
	 * @inheritDoc
	 */
	public function getInstancePathsWhereUserHasRole( UserIdentity $user, string $role ): array {
		$possibleGroups = $this->groupCreator->getInstanceGroups( $role );
		$userGroups = $this->getUserGroups( $user );
		$availableInstances = [];
		foreach ( $possibleGroups as $instancePath => $groups ) {
			$toCheck = array_keys( $groups );
			if ( array_intersect( $userGroups, $toCheck ) || in_array( 'sysop', $groups ) ) {
				if ( $instancePath === '_global' ) {
					// All instances allowed
					return array_diff( array_keys( $possibleGroups ), [ '_global' ] );
				}
				$availableInstances[] = $instancePath;
			}
		}

		return $availableInstances;
	}

	/**
	 * @param UserIdentity $user
	 * @return array
	 */
	private function getUserGroups( UserIdentity $user ): array {
		$db = $this->databaseFactory->createSharedUserDatabaseConnection();
		$res = $db->select(
			'user_groups',
			[ 'ug_group' ],
			[ 'ug_user' => $user->getId() ],
			__METHOD__
		);
		$db->close( __METHOD__ );

		$groups = [];
		foreach ( $res as $row ) {
			$groups[] = $row->ug_group;
		}
		return $groups;
	}

	/**
	 * @param UserIdentity $user
	 * @param string $role
	 * @param InstanceEntity $instance
	 * @return bool
	 */
	private function checkTeams( UserIdentity $user, string $role, InstanceEntity $instance ): bool {
		$userRoles = $this->teamQuery->getUserRolesForInstance( $user, $instance );
		return in_array( $role, $userRoles );
	}

}
