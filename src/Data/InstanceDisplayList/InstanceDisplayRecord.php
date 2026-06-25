<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use MWStake\MediaWiki\Component\DataStore\Record as BaseRecord;

class InstanceDisplayRecord extends BaseRecord {

	public const PATH = 'path';
	public const TITLE = 'title';
	public const FULLURL = 'fullurl';
	public const PINNED = 'pinned';
	public const INSTANCE_COLOR = 'instance_color';
	public const FAVOURITE = 'favourite';
	public const META_GROUP = 'meta_group';
}
