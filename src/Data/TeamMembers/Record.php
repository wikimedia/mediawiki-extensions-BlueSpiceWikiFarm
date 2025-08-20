<?php

namespace BlueSpice\WikiFarm\Data\TeamMembers;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const ID = 'id';
	public const NAME = 'name';
	public const DISPLAY_NAME = 'display_name';
	public const EXPIRATION = 'expiration';
	public const EXPIRATION_FORMATTED = 'expiration_formatted';
}
