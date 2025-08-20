<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\DirectInstanceStore;
use BlueSpice\WikiFarm\InstanceEntity;

class InstanceGroupCreator {

	/**
	 * @var DirectInstanceStore
	 */
	private $store;

	/**
	 * @param DirectInstanceStore $store
	 */
	public function __construct( DirectInstanceStore $store ) {
		$this->store = $store;
	}

	/**
	 * @param array $limitToRoles
	 * @return array
	 */
	public function getInstanceGroups( array $limitToRoles = [] ): array {
		$res = [
			'w' => $this->getGroupsAndRolesForInstancePath( 'w', $limitToRoles )
		];
		$instancePaths = $this->store->getInstancePathsQuick( [
			'sfi_status NOT IN (' . implode( ',', [
				'\'' . InstanceEntity::STATUS_ARCHIVED . '\'',
				'\'' . InstanceEntity::STATUS_SUSPENDED . '\'',
			] ) . ')'
		] );
		foreach ( $instancePaths as $instancePath ) {
			$res[$instancePath] = $this->getGroupsAndRolesForInstancePath( $instancePath, $limitToRoles );
		}
		// Add super groups
		$res['_global'] = $this->getGroupsAndRolesForInstancePath( '_global', $limitToRoles );

		return $res;
	}

	/**
	 * @param string $instancePath
	 * @param array $limitToRoles
	 * @return array
	 */
	public function getGroupsAndRolesForInstancePath( string $instancePath, array $limitToRoles = [] ): array {
		$res = [];
		foreach ( GroupAccessStore::ROLES as $group => $roles ) {
			if ( !empty( $limitToRoles ) && !in_array( $group, $limitToRoles ) ) {
				continue;
			}
			$groupName = $this->getGroupNameForUserRole( $instancePath, $group );
			$res[$groupName] = $roles;
		}
		return $res;
	}

	/**
	 * @param array $limitToRoles
	 * @return array
	 */
	public function getGlobalGroups( array $limitToRoles = [] ): array {
		$res = [];
		foreach ( GroupAccessStore::ROLES as $group => $roles ) {
			if ( !empty( $limitToRoles ) && !in_array( $group, $limitToRoles ) ) {
				continue;
			}
			$groupName = $this->getGroupNameForUserRole( '_global', $group );
			$res[$groupName] = $roles;
		}
		return $res;
	}

	/**
	 * @param string $instancePath
	 * @param string $userRole
	 * @return string
	 */
	public function getGroupNameForUserRole( string $instancePath, string $userRole ): string {
		if ( !isset( GroupAccessStore::ROLES[$userRole] ) ) {
			return '';
		}
		return $this->getGroupPrefixForInstancePath( $instancePath ) . $userRole;
	}

	/**
	 * @param string $instancePath
	 * @return string
	 */
	public function getGroupPrefixForInstancePath( string $instancePath ): string {
		return 'wiki_' . $instancePath . '_';
	}

	/**
	 * @param string $groupName
	 * @return string|null
	 */
	public function getRoleFromGroupName( string $groupName ): ?string {
		// Group name must match the pattern 'wiki_<instance_path>_<role>'
		$pattern = '/^wiki_(.*?)_(.*?)$/';
		if ( preg_match( $pattern, $groupName, $matches ) ) {
			$bits = explode( '_', $matches[2] );
			$role = array_pop( $bits );
			if ( isset( GroupAccessStore::ROLES[$role] ) ) {
				return $role;
			}
		}
		return null;
	}
}
