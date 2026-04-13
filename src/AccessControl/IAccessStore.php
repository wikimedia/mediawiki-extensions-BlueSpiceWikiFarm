<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;

interface IAccessStore {

	public const ROLES = [
		'reader' => [ 'reader' ],
		'editor' => [ 'reader', 'editor' ],
		'reviewer' => [ 'reader', 'editor', 'reviewer' ],
		'admin' => [ 'reader', 'editor', 'reviewer', 'admin' ],
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
