<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;

interface IAccessStore {

	public const ROLES = [
		'reader' => [ 'reader' ],
		'editor' => [ 'reader', 'editor' ],
		'maintainer' => [ 'reader', 'editor', 'reviewer', 'admin' ],
		'reviewer' => [ 'reader', 'editor', 'reviewer' ],
	];

	/**
	 * @param UserIdentity $user
	 * @param string $role
	 * @param InstanceEntity $instance
	 * @return bool
	 */
	public function userHasRoleOnInstance( UserIdentity $user, string $role, InstanceEntity $instance ): bool;

	/**
	 * @param UserIdentity $user
	 * @param string $role
	 * @return array
	 */
	public function getInstancePathsWhereUserHasRole( UserIdentity $user, string $role ): array;
}
