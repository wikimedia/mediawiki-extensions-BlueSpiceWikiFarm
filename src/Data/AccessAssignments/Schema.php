<?php

namespace BlueSpice\WikiFarm\Data\AccessAssignments;

use MWStake\MediaWiki\Component\DataStore\FieldType;

class Schema extends \MWStake\MediaWiki\Component\DataStore\Schema {
	public function __construct() {
		parent::__construct( [
			Record::ENTITY_TYPE => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::ENTITY_KEY => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::ROLE => [
				self::FILTERABLE => false,
				self::SORTABLE => false,
				self::TYPE => FieldType::STRING
			],
			Record::IS_GLOBAL_ASSIGNMENT => [
				self::FILTERABLE => false,
				self::SORTABLE => true,
				self::TYPE => FieldType::BOOLEAN
			],
		] );
	}
}
