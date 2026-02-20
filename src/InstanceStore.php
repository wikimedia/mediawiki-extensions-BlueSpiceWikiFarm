<?php

namespace BlueSpice\WikiFarm;

use DateTime;
use MediaWiki\Config\Config;
use Random\RandomException;

class InstanceStore extends DirectInstanceStore {

	/**
	 * @param string $path
	 * @param Config $farmConfig
	 * @return InstanceEntity
	 * @throws RandomException
	 */
	public function newEmptyInstance( string $path, Config $farmConfig ): InstanceEntity {
		$instanceId = $this->generateId();
		$dbName = $farmConfig->get( 'useSharedDB' ) ?
			$farmConfig->get( 'sharedDBname' ) :
			$farmConfig->get( 'dbPrefix' ) . $instanceId;
		$dbPrefix = $farmConfig->get( 'useSharedDB' ) ? $instanceId . '_' : '';
		return new InstanceEntity(
			$instanceId, $path, '', new DateTime(), new DateTime(),
			InstanceEntity::STATUS_INIT, $dbName, $dbPrefix, [ 'group' => '', 'keywords' => [], 'desc' => '' ], []
		);
	}

	/**
	 * @return string
	 * @throws RandomException
	 */
	public function generateId() {
		$bytes = random_bytes( 4 );
		$hex = bin2hex( $bytes );
		return substr( $hex, 0, 8 );
	}
}
