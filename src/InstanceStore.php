<?php

namespace BlueSpice\WikiFarm;

use DateTime;
use MediaWiki\Config\Config;
use Random\RandomException;

class InstanceStore extends DirectInstanceStore {

	/**
	 * Resolve a case-insensitive interwiki prefix without changing path lookup semantics.
	 *
	 * @param string $prefix
	 * @return InstanceEntity|null
	 */
	public function getInstanceByInterwiki( string $prefix ): ?InstanceEntity {
		$prefix = mb_strtolower( $prefix );
		if ( $prefix === 'w' ) {
			return $this->getInstanceByPath( 'w' );
		}
		foreach ( $this->getAllInstances() as $instance ) {
			if ( $instance->getInterwiki() === $prefix ) {
				return $instance;
			}
		}
		return null;
	}

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
		$colors = Setup::getInstanceBadgeColors();
		$randomColor = $colors[array_rand( $colors )];
		return new InstanceEntity(
			$instanceId,
			$path,
			'',
			new DateTime(),
			new DateTime(),
			InstanceEntity::STATUS_INIT, $dbName, $dbPrefix,
			[
				'group' => '',
				'keywords' => [],
				'desc' => '',
				'creator' => '',
				'instanceColor' => $randomColor
			],
			[]
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
