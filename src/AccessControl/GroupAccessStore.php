<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use MediaWiki\Config\Config;
use MediaWiki\User\UserIdentity;
use Wikimedia\ObjectCache\BagOStuff;
use Wikimedia\ObjectCache\HashBagOStuff;

class GroupAccessStore implements IAccessStore {

	private const HIERARCHY = [
		'reader' => [ 'editor', 'maintainer', 'reviewer' ],
		'editor' => [ 'maintainer', 'reviewer' ],
		'maintainer' => [ 'reviewer' ],
	];

	public const ACCESS_LEVELS = [
		'public', 'protected', 'private'
	];

	/** @var BagOStuff */
	private $operatingCache;

	/**
	 * @param ManagementDatabaseFactory $databaseFactory
	 * @param InstanceGroupCreator $groupCreator
	 * @param TeamQuery $teamQuery
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly ManagementDatabaseFactory $databaseFactory,
		private readonly InstanceGroupCreator $groupCreator,
		private readonly TeamQuery $teamQuery,
		private readonly Config $farmConfig
	) {
		$this->operatingCache = new HashBagOStuff();
	}

	/**
	 * @inheritDoc
	 */
	public function userHasRoleOnInstance( UserIdentity $user, string $role, InstanceEntity $instance ): bool {
		$cc = $this->operatingCache->makeKey( 'access', $user->getName(), $instance->getId(), $role );
		if ( $this->operatingCache->hasKey( $cc ) ) {
			return $this->operatingCache->get( $cc );
		}

		$db = $this->databaseFactory->createSharedUserDatabaseConnection();
		$groups = [
			$this->groupCreator->getGroupNameForUserRole( $instance->getPath(), $role ),
			...$this->getHigherGroups( $instance->getPath(), $role ),
		];
		$globalGroups = [
			$this->groupCreator->getGroupNameForUserRole( '_global', $role ),
			...$this->getHigherGroups( '_global', $role ),
		];
		$res = $db->selectRow(
			'user_groups',
			[ 'ug_user' ],
			[
				'ug_user' => $user->getId(),
				'ug_group IN (' . $db->makeList( array_merge( $groups, $globalGroups ) ) . ')'
			],
			__METHOD__
		);
		$db->close( __METHOD__ );
		$hasRole = $res !== false;
		if ( !$hasRole ) {
			$hasRole = $this->checkTeams( $user, $role, $instance );
		}

		$this->operatingCache->set( $cc, $hasRole );
		return $hasRole;
	}

	/**
	 * @inheritDoc
	 */
	public function getInstancePathsWhereUserHasRole( UserIdentity $user, string $role ): array {
		$cc = $this->operatingCache->makeKey( 'access-all', $user->getName(), $role );
		if ( $this->operatingCache->hasKey( $cc ) ) {
			return $this->operatingCache->get( $cc );
		}
		$possibleGroups = $this->groupCreator->getInstanceGroups( [ $role, ...$this->getHigherRoles( $role ) ] );
		$userGroups = $this->getUserGroups( $user );
		$availableInstances = [];
		$superUserGroups = $this->farmConfig->get( 'superAccessGroups' ) ?? [ 'sysop' ];
		foreach ( $possibleGroups as $instancePath => $groups ) {
			$toCheck = array_keys( $groups );
			$isSuperUser = !empty( array_intersect( $superUserGroups, $userGroups ) );
			if ( array_intersect( $userGroups, $toCheck ) || $isSuperUser ) {
				if ( $instancePath === '_global' ) {
					// All instances allowed
					return array_diff( array_keys( $possibleGroups ), [ '_global' ] );
				}
				$availableInstances[] = $instancePath;
			}
		}
		$this->operatingCache->set( $cc, $availableInstances );
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
		$roles = array_merge( [ $role ], $this->getHigherRoles( $role ) );
		return !empty( array_intersect( $roles, $userRoles ) );
	}

	/**
	 * @param string $role
	 * @return array
	 */
	private function getHigherRoles( string $role ): array {
		return static::HIERARCHY[$role] ?? [];
	}

	/**
	 * @param string $path
	 * @param string $role
	 * @return array
	 */
	private function getHigherGroups( string $path, string $role ): array {
		return array_map( function ( $role ) use ( $path ) {
			return $this->groupCreator->getGroupNameForUserRole( $path, $role );
		}, $this->getHigherRoles( $role ) );
	}

}
