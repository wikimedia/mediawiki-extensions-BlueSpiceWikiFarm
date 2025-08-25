<?php

namespace BlueSpice\WikiFarm\Data\Teams;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const ID = 'id';
	public const NAME = 'name';
	public const DESCRIPTION = 'description';
	public const MEMBER_COUNT = 'memberCount';
}
