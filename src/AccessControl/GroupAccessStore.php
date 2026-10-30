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
		'reader' => [ 'editor', 'reviewer', 'admin' ],
		'editor' => [ 'reviewer', 'admin' ],
		'reviewer' => [ 'admin' ],
	];

	public const ACCESS_LEVELS = [
		'public', 'protected', 'private'
	];

	/** @var BagOStuff */
	private $operatingCache;

	/**
	 * @param ManagementDatabaseFactory $databaseFactory
	 * @param InstanceGroupCreator $groupCreator
	 * @param GroupRoleQuery $groupRoleQuery
	 * @param WikiAccessLookup $wikiAccessLookup
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly ManagementDatabaseFactory $databaseFactory,
		private readonly InstanceGroupCreator $groupCreator,
		private readonly GroupRoleQuery $groupRoleQuery,
		private readonly WikiAccessLookup $wikiAccessLookup,
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
		if ( $role === 'reader' && $this->instanceHasReaderAccessByLevel( $instance ) ) {
			$this->operatingCache->set( $cc, true );
			return true;
		}

		$db = $this->databaseFactory->createSharedUserDatabaseConnection();
		$allowedGroups = $this->getAllowedGroupsForInstance( $instance, $role );
		$res = $db->selectRow(
			'user_groups',
			[ 'ug_user' ],
			[
				'ug_user' => $user->getId(),
				'ug_group IN (' . $db->makeList( $allowedGroups ) . ')'
			],
			__METHOD__
		);
		$db->close( __METHOD__ );
		$hasRole = $res !== false;
		if ( !$hasRole ) {
			$hasRole = $this->checkGroupRoles( $user, $role, $instance );
		}

		$this->operatingCache->set( $cc, $hasRole );
		return $hasRole;
	}

	/**
	 * Get all users unavailable for given instance
	 *
	 * @param InstanceEntity $instance
	 * @param array $additionalGroups groups allowed by group roles
	 * @return array
	 */
	public function getExcludedUsers( InstanceEntity $instance, array $additionalGroups = [] ): array {
		$allowedGroups = $this->getAllowedGroupsForInstance( $instance, 'reader' );
		if ( !empty( $additionalGroups ) ) {
			$allowedGroups = array_merge( $allowedGroups, $additionalGroups );
		}

		$db = $this->databaseFactory->createSharedUserDatabaseConnection();
		$allowedUsers = $db->newSelectQueryBuilder()
			->select( 'u.user_id' )
			->distinct()
			->from( 'user', 'u' )
			->join(
				'user_groups',
				'ug',
				[
					'ug.ug_user = u.user_id',
					'ug.ug_group' => $allowedGroups,
				]
			)
			->caller( __METHOD__ )
			->fetchFieldValues();

		return $db->newSelectQueryBuilder()
			->select( 'user_name' )
			->from( 'user' )
			->where( [ 'user_id NOT IN (' . $db->makeList( $allowedUsers ) . ')' ] )
			->caller( __METHOD__ )
			->fetchFieldValues();
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
		$possibleGroupRoles = $this->groupRoleQuery->getUserRolesForInstancePaths( $user, array_keys( $possibleGroups ) );
		$userGroups = $this->getUserGroups( $user );
		$availableInstances = [];
		$superUserGroups = $this->farmConfig->get( 'superAccessGroups' ) ?? [];
		foreach ( $possibleGroups as $instancePath => $groups ) {
			$allowed = false;
			$toCheck = array_keys( $groups );
			$isSuperUser = !empty( array_intersect( $superUserGroups, $userGroups ) );
			if ( array_intersect( $userGroups, $toCheck ) || $isSuperUser ) {
				if ( $instancePath === '_global' ) {
					// All instances allowed
					return array_diff( array_keys( $possibleGroups ), [ '_global' ] );
				}
				$allowed = true;
			}
			if ( !$allowed ) {
				$groupRoles = $possibleGroupRoles[$instancePath] ?? [];
				$allowedRoles = array_merge( [ $role ], $this->getHigherRoles( $role ) );

				if ( !empty( array_intersect( $allowedRoles, $groupRoles ) ) ) {
					$allowed = true;
				}
			}
			if ( $allowed ) {
				$availableInstances[] = $instancePath;
			}
		}
		if ( $role === 'reader' ) {
			$availableInstances = array_merge(
				$availableInstances,
				$this->wikiAccessLookup->getInstancePathsForLevels( [ 'public', 'protected' ] )
			);
		}
		$availableInstances = array_values( array_unique( $availableInstances ) );
		$this->operatingCache->set( $cc, $availableInstances );
		return $availableInstances;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return bool
	 */
	private function instanceHasReaderAccessByLevel( InstanceEntity $instance ): bool {
		return in_array(
			$this->wikiAccessLookup->getAccessLevelForInstance( $instance ),
			[ 'public', 'protected' ]
		);
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
	private function checkGroupRoles( UserIdentity $user, string $role, InstanceEntity $instance ): bool {
		$userRoles = $this->groupRoleQuery->getUserRolesForInstancePath( $user, $instance->getPath() );
		$roles = array_merge( [ $role ], $this->getHigherRoles( $role ) );
		return !empty( array_intersect( $roles, $userRoles ) );
	}

	/**
	 * @param string $role
	 * @return array
	 */
	public function getHigherRoles( string $role ): array {
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

	/**
	 * @param InstanceEntity|null $instance
	 * @param string $role
	 * @return array
	 */
	private function getAllowedGroupsForInstance( ?InstanceEntity $instance, string $role ): array {
		$groups = $instance ? [
			$this->groupCreator->getGroupNameForUserRole( $instance->getPath(), $role ),
			...$this->getHigherGroups( $instance->getPath(), $role ),
		] : [];
		$globalGroups = [
			$this->groupCreator->getGroupNameForUserRole( '_global', $role ),
			...$this->getHigherGroups( '_global', $role ),
		];
		$superAccessGroups = $this->farmConfig->get( 'superAccessGroups' ) ?? [];
		return array_merge( $groups, $globalGroups, $superAccessGroups );
	}

}
