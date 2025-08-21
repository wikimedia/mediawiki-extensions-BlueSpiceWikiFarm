<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncOATHAuthUsers implements ISyncUserTable {

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
		return "OATHAuthUser < user_id {$row['id']} - module {$row['module']} >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		$db->insert(
			'oathauth_users',
			$row,
			__METHOD__
		);

		// There is no autoincrement ID in this table
		return 0;
	}

	/**
	 * @inheritDoc
	 */
	public function existsInDb( IDatabase $db, array $row ): bool {
		$userId = $db->selectField(
			'oathauth_users',
			'id',
			[
				'id' => $row['id']
			],
			__METHOD__
		);

		return (bool)$userId;
	}

	/**
	 * @inheritDoc
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int {
		return 0;
	}
}
