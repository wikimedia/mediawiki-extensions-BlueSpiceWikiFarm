<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\UserManager\Hook\BSUserManagerBeforeAddGroupHook;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\Authority;

class FilterImplicitGroupCreation implements BSUserManagerBeforeAddGroupHook {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct( private readonly Config $farmConfig ) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBSUserManagerBeforeAddGroup( string &$name, Authority $actor ) {
		if ( !$this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			return;
		}
		// Disallow creation of groups that match the implicit wiki instance group pattern
		if ( preg_match( '/^wiki_.*_(reader|editor|reviewer|admin)$/', $name ) ) {
			return false;
		}
	}

}
