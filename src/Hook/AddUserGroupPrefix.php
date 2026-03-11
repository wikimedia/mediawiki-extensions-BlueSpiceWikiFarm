<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\UserManager\Hook\BSUserManagerBeforeAddGroupHook;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\Authority;

class AddUserGroupPrefix implements BSUserManagerBeforeAddGroupHook {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct( private readonly Config $farmConfig ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerBeforeAddGroup( string &$name, Authority $actor ) {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$name = 'team-' . $name;
		}
	}

}
