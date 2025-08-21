<?php

namespace BlueSpice\WikiFarm\TitleSearch\Store;

use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\TitleSchema;
use MWStake\MediaWiki\Component\DataStore\FieldType;

class FarmTitleSchema extends TitleSchema {

	/**
	 * @param array $fields
	 */
	public function __construct( array $fields = [] ) {
		parent::__construct( array_merge( [
			FarmTitleRecord::FARM_INSTANCE => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			FarmTitleRecord::FARM_INSTANCE_DISPLAY => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			FarmTitleRecord::FARM_INSTANCE_INTERWIKI => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			FarmTitleRecord::FARM_INSTANCE_IS_LOCAL => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::BOOLEAN
			]
		], $fields ) );
	}
}
