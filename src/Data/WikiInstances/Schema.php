<?php

namespace BlueSpice\WikiFarm\Data\WikiInstances;

use MWStake\MediaWiki\Component\DataStore\FieldType;

class Schema extends \MWStake\MediaWiki\Component\DataStore\Schema {
	public function __construct() {
		parent::__construct( [
			Record::PATH => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::CTIME => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::DATE
			],
			Record::MTIME => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::DATE
			],
			Record::TITLE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::SUSPENDED => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::BOOLEAN
			],
			Record::NOTSEARCHABLE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::BOOLEAN
			],
			Record::FULLURL => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::META_GROUP => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::AUTO
			],
			Record::IS_COMPLETE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::BOOLEAN
			],
			Record::IS_SYSTEM => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::BOOLEAN
			],
			Record::INSTANCE_COLOR => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			]
		] );
	}
}
