<?php

namespace BlueSpice\WikiFarm\Data\Teams;

use MWStake\MediaWiki\Component\DataStore\FieldType;

class Schema extends \MWStake\MediaWiki\Component\DataStore\Schema {
	public function __construct() {
		parent::__construct( [
			Record::ID => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
			Record::NAME => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::DESCRIPTION => [
				self::FILTERABLE => true,
				self::SORTABLE => true,
				self::TYPE => FieldType::STRING
			],
			Record::MEMBER_COUNT => [
				self::FILTERABLE => false,
				self::SORTABLE => true,
				self::TYPE => FieldType::INT
			],
		] );
	}
}
