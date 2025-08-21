<?php

namespace BlueSpice\WikiFarm\Data\WikiInstances;

class Record extends \MWStake\MediaWiki\Component\DataStore\Record {
	public const PATH = 'path';
	public const MTIME = 'mtime';
	public const CTIME = 'ctime';
	public const TITLE = 'title';
	public const FULLURL = 'fullurl';
	public const SUSPENDED = 'suspended';
	public const NOTSEARCHABLE = 'notsearchable';
	public const META_GROUP = 'meta_group';
	public const IS_COMPLETE = 'is_complete';
	public const IS_SYSTEM = 'is_system';
}
