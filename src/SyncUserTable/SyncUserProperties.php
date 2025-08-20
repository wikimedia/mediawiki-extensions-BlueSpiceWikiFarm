<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncUserProperties implements ISyncUserTable {

	/**
	 * @inheritDoc
	 */
	public function getRelatedTables(): array {
		return [];
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryKey(): string {
		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function getStringIdentifier( array $row ): string {
		return "UserProperty < {$row['up_user']} - {$row['up_property']} >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		$db->insert(
			'user_properties',
			$row,
			__METHOD__
		);

		// There is no autoincrement ID in this table
		// As soon as this table is just used for mapping
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	public function existsInDb( IDatabase $db, array $row ): bool {
		$propertyName = $db->selectField(
			'user_properties',
			'up_property',
			[
				'up_user' => $row['up_user'],
				'up_property' => $row['up_property']
			],
			__METHOD__
		);

		return (bool)$propertyName;
	}

	/**
	 * @inheritDoc
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int {
		return 0;
	}
}
