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
	 * @param string $limitToUserRole
	 * @return array
	 */
	public function getInstanceGroups( string $limitToUserRole = '' ): array {
		$res = [
			'w' => $this->getGroupsAndRolesForInstancePath( 'w', $limitToUserRole )
		];
		$instancePaths = $this->store->getInstancePathsQuick( [
			'sfi_status NOT IN (' . implode( ',', [
				'\'' . InstanceEntity::STATUS_ARCHIVED . '\'',
				'\'' . InstanceEntity::STATUS_SUSPENDED . '\'',
			] ) . ')'
		] );
		foreach ( $instancePaths as $instancePath ) {
			$res[$instancePath] = $this->getGroupsAndRolesForInstancePath( $instancePath, $limitToUserRole );
		}
		// Add super groups
		$res['_global'] = $this->getGroupsAndRolesForInstancePath( '_global', $limitToUserRole );

		return $res;
	}

	/**
	 * @param string $instancePath
	 * @param string $limitToUserRole
	 * @return array
	 */
	public function getGroupsAndRolesForInstancePath( string $instancePath, string $limitToUserRole = '' ): array {
		$res = [];
		foreach ( GroupAccessStore::ROLES as $group => $roles ) {
			if ( $limitToUserRole && $limitToUserRole !== $group ) {
				continue;
			}
			$groupName = $this->getGroupNameForUserRole( $instancePath, $group );
			$res[$groupName] = $roles;
		}
		return $res;
	}

	/**
	 * @param string $limitToUserRole
	 * @return array
	 */
	public function getGlobalGroups( string $limitToUserRole = '' ): array {
		$res = [];
		foreach ( GroupAccessStore::ROLES as $group => $roles ) {
			if ( $limitToUserRole && $limitToUserRole !== $group ) {
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
