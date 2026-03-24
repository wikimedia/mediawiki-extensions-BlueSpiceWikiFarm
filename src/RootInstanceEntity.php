<?php

namespace BlueSpice\WikiFarm;

use DateTime;
use MediaWiki\Message\Message;

class RootInstanceEntity extends InstanceEntity {

	/**
	 * @param string $dbName
	 * @param string $dbPrefix
	 */
	public function __construct( string $dbName = '<root>', string $dbPrefix = '' ) {
		parent::__construct(
			'w', 'w', 'w',
			new DateTime(), new DateTime(), static::STATUS_READY, $dbName, $dbPrefix, [], []
		);
	}

	/**
	 * @return string
	 */
	public function getDisplayName(): string {
		return Message::newFromKey( 'wikifarm-main-wiki-name' )->text();
	}
}
