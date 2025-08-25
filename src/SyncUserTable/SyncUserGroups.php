<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncUserGroups implements ISyncUserTable {

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
		return "UserGroup < {$row['ug_user']} - {$row['ug_group']} >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		$db->insert(
			'user_groups',
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
		$groupName = $db->selectField(
			'user_groups',
			'ug_group',
			[
				'ug_user' => $row['ug_user'],
				'ug_group' => $row['ug_group']
			],
			__METHOD__
		);

		return (bool)$groupName;
	}

	/**
	 * @inheritDoc
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int {
		return 0;
	}
}
