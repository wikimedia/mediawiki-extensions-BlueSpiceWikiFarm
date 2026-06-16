<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\UserManager\Hook\BSUserManagerGroupAddedHook;
use BlueSpice\UserManager\Hook\BSUserManagerGroupDeletedHook;
use BlueSpice\UserManager\Hook\BSUserManagerGroupEditedHook;
use BlueSpice\WikiFarm\AccessControl\GroupListStore;
use MediaWiki\Permissions\Authority;

class UpdateGroupList implements
	BSUserManagerGroupAddedHook,
	BSUserManagerGroupEditedHook,
	BSUserManagerGroupDeletedHook
{

	/**
	 * @param GroupListStore $groupListStore
	 */
	public function __construct(
		private readonly GroupListStore $groupListStore
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerGroupAdded( string $name, Authority $actor ) {
		$this->groupListStore->addGroup( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerGroupDeleted( string $name, Authority $actor ) {
		$this->groupListStore->removeGroup( $name );
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerGroupEdited( string $oldName, string $newName, Authority $actor ) {
		$this->groupListStore->removeGroup( $oldName );
		$this->groupListStore->addGroup( $newName );
	}
}
