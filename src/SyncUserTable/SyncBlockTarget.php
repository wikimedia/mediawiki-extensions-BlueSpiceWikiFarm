<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncBlockTarget implements ISyncUserTable {

	/**
	 * @inheritDoc
	 */
	public function getRelatedTables(): array {
		return [
			'ipblocks_restrictions' => 'ir_ipb_id',
			'block' => 'bl_target'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryKey(): string {
		return 'bt_id';
	}

	/**
	 * @inheritDoc
	 */
	public function getStringIdentifier( array $row ): string {
		return "Block < {$row['bt_id']} - user {$row['bt_user']} >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		unset( $row['bt_id'] );

		$db->insert(
			'block_target',
			$row,
			__METHOD__
		);

		return $db->insertId();
	}

	/**
	 * @inheritDoc
	 */
	public function existsInDb( IDatabase $db, array $row ): bool {
		$blockId = $db->selectField(
			'block_target',
			'bt_id',
			[
				'bt_user' => $row['bt_user'],
			],
			__METHOD__
		);

		return (bool)$blockId;
	}

	/**
	 * @inheritDoc
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int {
		return $db->selectField(
			'block_target',
			'bt_id',
			[
				'bt_user' => $row['bt_user'],
			],
			__METHOD__
		);
	}
}
