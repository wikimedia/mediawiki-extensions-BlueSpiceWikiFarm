<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncMwsUserIndex implements ISyncUserTable {

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
		return "MwsUserIndexEntry < {$row['mui_user_id']} - {$row['mui_user_name']} >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		$db->insert(
			'mws_user_index',
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
			'mws_user_index',
			'mui_user_id',
			[
				'mui_user_name' => $row['mui_user_name']
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
