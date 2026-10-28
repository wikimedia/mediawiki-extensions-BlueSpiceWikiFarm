<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use MediaWiki\Config\Config;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class PromoteRootCreator extends LoggedUpdateMaintenance {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doDBUpdates() {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || FARMER_IS_ROOT_WIKI_CALL === false ) {
			// Only run on root wiki
			return true;
		}
		/** @var Config $farmConfig */
		$farmConfig = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm._Config' );
		if ( !$farmConfig->get( 'useGlobalAccessControl' ) ) {
			return true;
		}

		$this->output( 'Promoting root wiki creator to admin...' );
		$user = $this->getServiceContainer()->getUserFactory()->newFromId( 1 );
		if ( !$user->isRegistered() ) {
			$this->output( "failed - user not registered\n" );
			// Dont know how this could happen...
			return false;
		}

		/** @var InstanceGroupCreator $groupCreator */
		$groupCreator = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceGroupCreator' );
		$groupName = $groupCreator->getGroupNameForUserRole( 'w', IAccessStore::ROLE_ADMIN );

		$groupManager = $this->getServiceContainer()->getUserGroupManager();
		$userGroups = $groupManager->getUserGroups( $user );
		if ( in_array( $groupName, $userGroups ) ) {
			$this->output( "done. Already in $groupName\n" );
			return true;
		}
		if ( $groupManager->addUserToGroup( $user, $groupName ) ) {
			$this->output( "done. Added to $groupName\n" );
			return true;
		}

		$this->output( "failed - user not added to group $groupName\n" );
		return false;
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-promote-root-creator';
	}
}

$maintClass = PromoteRootCreator::class;
require_once RUN_MAINTENANCE_IF_MAIN;
