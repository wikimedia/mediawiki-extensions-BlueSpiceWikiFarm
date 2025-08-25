<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncFormerUserGroups implements ISyncUserTable {

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
		return "UserFormerGroup < {$row['ufg_user']} - {$row['ufg_group']} >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		$db->insert(
			'user_former_groups',
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
			'user_former_groups',
			'ufg_group',
			[
				'ufg_user' => $row['ufg_user'],
				'ufg_group' => $row['ufg_group']
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
