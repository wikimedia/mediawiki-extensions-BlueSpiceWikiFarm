<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\WikiFarm\SyncUserTable\SyncActor;
use BlueSpice\WikiFarm\SyncUserTable\SyncBlockTarget;
use BlueSpice\WikiFarm\SyncUserTable\SyncFormerUserGroups;
use BlueSpice\WikiFarm\SyncUserTable\SyncMwsUserIndex;
use BlueSpice\WikiFarm\SyncUserTable\SyncOATHAuthUsers;
use BlueSpice\WikiFarm\SyncUserTable\SyncUser;
use BlueSpice\WikiFarm\SyncUserTable\SyncUserGroups;
use BlueSpice\WikiFarm\SyncUserTable\SyncUserProperties;
use Exception;

class SyncUserTableFactory {

	/**
	 * @param string $userTable
	 * @return ISyncUserTable
	 */
	public static function getSyncClass( string $userTable ): ISyncUserTable {
		switch ( $userTable ) {
			case 'user':
				$syncClassObj = new SyncUser();
				break;
			case 'actor':
				$syncClassObj = new SyncActor();
				break;
			case 'user_groups':
				$syncClassObj = new SyncUserGroups();
				break;
			case 'user_former_groups':
				$syncClassObj = new SyncFormerUserGroups();
				break;
			case 'user_properties':
				$syncClassObj = new SyncUserProperties();
				break;
			case 'block_target':
				$syncClassObj = new SyncBlockTarget();
				break;
			case 'mws_user_index':
				$syncClassObj = new SyncMwsUserIndex();
				break;
			case 'oathauth_users':
				$syncClassObj = new SyncOATHAuthUsers();
				break;
			default:
				throw new Exception( 'Unknown table to sync!' );
		}

		return $syncClassObj;
	}
}
