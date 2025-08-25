<?php

namespace BlueSpice\WikiFarm\Data\AccessAssignments;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const ENTITY_TYPE = 'entity_type';
	public const ENTITY_KEY = 'entity_key';
	public const ROLE = 'role';
	public const IS_GLOBAL_ASSIGNMENT = 'is_global_assignment';
}
