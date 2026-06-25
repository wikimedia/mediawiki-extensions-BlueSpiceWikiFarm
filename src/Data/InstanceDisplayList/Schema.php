<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use MWStake\MediaWiki\Component\DataStore\FieldType;

class Schema extends \MWStake\MediaWiki\Component\DataStore\Schema {
	public function __construct() {
		parent::__construct( [
			InstanceDisplayRecord::PATH => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			InstanceDisplayRecord::TITLE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			InstanceDisplayRecord::FULLURL => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			InstanceDisplayRecord::INSTANCE_COLOR => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			InstanceDisplayRecord::FAVOURITE => [
				self::FILTERABLE => true,
				self::SORTABLE => false,
				self::TYPE => FieldType::BOOLEAN
			],
			InstanceDisplayRecord::META_GROUP => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::AUTO
			],
		] );
	}
}
