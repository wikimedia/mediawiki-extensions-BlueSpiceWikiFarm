<?php

namespace BlueSpice\WikiFarm\TitleSearch\Store;

use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\TitleRecord;

class FarmTitleRecord extends TitleRecord {
	public const FARM_INSTANCE = '_instance';
	public const FARM_INSTANCE_DISPLAY = '_instance_display';
	public const FARM_INSTANCE_INTERWIKI = '_instance_interwiki';
	public const FARM_INSTANCE_IS_LOCAL = '_instance_is_local';
}
