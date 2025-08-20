<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncActor implements ISyncUserTable {

	/**
	 * @inheritDoc
	 */
	public function getRelatedTables(): array {
		return [
			'archive' => 'ar_actor',
			'block' => 'bl_by_actor',
			'image' => 'img_actor',
			'oldimage' => 'oi_actor',
			'filearchive' => 'fa_actor',
			'recentchanges' => 'rc_actor',
			'logging' => 'log_actor',
			'echo_event' => 'event_agent_id',
			'page_checkout_event' => 'pce_actor_id',
			'revision' => 'rev_actor',
			'revision_actor_temp' => 'revactor_actor'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryKey(): string {
		return 'actor_id';
	}

	/**
	 * @inheritDoc
	 */
	public function getStringIdentifier( array $row ): string {
		return "Actor < {$row['actor_name']} (user_id = {$row['actor_user']}) >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		unset( $row['actor_id'] );

		$db->insert(
			'actor',
			$row,
			__METHOD__
		);

		return $db->insertId();
	}

	/**
	 * @inheritDoc
	 */
	public function existsInDb( IDatabase $db, array $row ): bool {
		$actorId = $db->selectField(
			'actor',
			'actor_id',
			[
				'actor_name' => $row['actor_name']
			],
			__METHOD__
		);

		return (bool)$actorId;
	}

	/**
	 * @inheritDoc
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int {
		return $db->selectField(
			'actor',
			'actor_id',
			[
				'actor_name' => $row['actor_name']
			],
			__METHOD__
		);
	}
}
