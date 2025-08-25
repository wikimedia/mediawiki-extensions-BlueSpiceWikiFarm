<?php

namespace BlueSpice\WikiFarm\AccessControl;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;

class NullAccessStore implements IAccessStore {
	/**
	 * @inheritDoc
	 */
	public function userHasRoleOnInstance( UserIdentity $user, string $role, InstanceEntity $instance ): bool {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function getInstancePathsWhereUserHasRole( UserIdentity $user, string $role ): array {
		return [];
	}
}
