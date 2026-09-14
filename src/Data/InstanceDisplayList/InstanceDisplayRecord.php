<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use MWStake\MediaWiki\Component\DataStore\Record as BaseRecord;

class InstanceDisplayRecord extends BaseRecord {

	public const PATH = 'path';
	public const PATH_LABEL = 'path_label';
	public const TITLE = 'title';
	public const FULLURL = 'fullurl';
	public const PINNED = 'pinned';
	public const INSTANCE_COLOR = 'instance_color';
	public const INSTANCE_COLOR_LIGHT_TEXT = 'instance_color_light_text';
	public const FAVOURITE = 'favourite';
	public const META_GROUP = 'meta_group';
	public const IS_SYSTEM = 'is_system';
}
