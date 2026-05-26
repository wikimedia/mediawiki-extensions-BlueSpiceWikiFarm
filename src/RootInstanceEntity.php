<?php

namespace BlueSpice\WikiFarm;

use DateTime;
use MediaWiki\Message\Message;

class RootInstanceEntity extends InstanceEntity {

	private const MAIN_INSTANCE_COLOR = [
		'background' => '#3e5389'
	];

	/**
	 * @param string $dbName
	 * @param string $dbPrefix
	 * @param string $wikiId
	 */
	public function __construct( string $dbName = '<root>', string $dbPrefix = '', string $wikiId = '' ) {
		parent::__construct(
			'w', 'w', 'w',
			new DateTime(), new DateTime(), static::STATUS_READY, $dbName, $dbPrefix,
			[ 'instanceColor' => self::MAIN_INSTANCE_COLOR ], []
		);
	}

	/**
	 * @return string
	 */
	public function getDisplayName(): string {
		return Message::newFromKey( 'wikifarm-main-wiki-name' )->text();
	}
}
