<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\UserManager\Hook\BSUserManagerGroupDeletedHook;
use BlueSpice\UserManager\Hook\BSUserManagerGroupEditedHook;
use BlueSpice\WikiFarm\AccessControl\GroupRoleManager;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\Authority;

class UpdateGroupAssignmentsOnGroupChange implements
	BSUserManagerGroupEditedHook,
	BSUserManagerGroupDeletedHook
{

	/**
	 * @param GroupRoleManager $groupRoleManager
	 * @param Config $config
	 */
	public function __construct(
		private readonly GroupRoleManager $groupRoleManager,
		private readonly Config $config
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerGroupDeleted( string $name, Authority $actor ) {
		if ( !$this->isEnabled() ) {
			return;
		}
		$this->groupRoleManager->onGroupDeleted( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerGroupEdited( string $oldName, string $newName, Authority $actor ) {
		if ( !$this->isEnabled() ) {
			return;
		}
		$this->groupRoleManager->onGroupRenamed( $oldName, $newName );
	}

	/**
	 * @return bool
	 */
	private function isEnabled(): bool {
		return $this->config->get( 'shareUsers' ) && $this->config->get( 'useGlobalAccessControl' );
	}
}
