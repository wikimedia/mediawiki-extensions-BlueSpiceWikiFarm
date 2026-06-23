<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;

interface IAccessStore {

	public const ROLE_READER = 'reader';
	public const ROLE_EDITOR = 'editor';
	public const ROLE_REVIEWER = 'reviewer';
	public const ROLE_ADMIN = 'admin';

	public const ROLES = [
		self::ROLE_READER => [ 'reader' ],
		self::ROLE_EDITOR => [ 'reader', 'editor' ],
		self::ROLE_REVIEWER => [ 'reader', 'editor', 'reviewer' ],
		self::ROLE_ADMIN => [ 'reader', 'editor', 'reviewer', 'admin' ],
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
