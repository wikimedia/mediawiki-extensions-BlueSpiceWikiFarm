<?php

namespace BlueSpice\WikiFarm\SyncUserTable;

use BlueSpice\WikiFarm\ISyncUserTable;
use Wikimedia\Rdbms\IDatabase;

class SyncUser implements ISyncUserTable {

	/**
	 * @inheritDoc
	 */
	public function getRelatedTables(): array {
		return [
			'bot_passwords' => 'bp_user',

			'bs_extendedsearch_history' => 'esh_user',
			'bs_extendedsearch_relevance' => 'esr_user',
			'bs_privacy_request' => 'pr_user',
			'bs_rating' => 'rat_userid',
			'bs_readconfirmation' => 'rc_user_id',
			'bs_readers' => 'readers_user_id',
			'bs_reminder' => 'rem_user_id',
			'bs_whoisonline' => 'wo_user_id',

			'echo_email_batch' => 'eeb_user_id',
			'echo_notification' => 'notification_user',
			'echo_push_subscription' => 'eps_user',
			'echo_unread_wikis' => 'euw_user',
			'filearchive' => 'fa_deleted_user',
			'form_data' => 'fd_user',

			// This table has two referenced to "user_id" columns, but it still should work
			'invitesignup' => [ 'is_inviter', 'is_invitee' ],

			'page_checkout_locks' => 'pcl_user_id',
			'protected_titles' => 'pt_user',
			'stable_points' => 'sp_user',
			'uploadstash' => 'us_user',

			'user_newtalk' => 'user_id',

			'watchlist' => 'wl_user',
			'webdav_static_tokens' => 'wdst_user_id',
			'webdav_tokens' => 'wdt_user_id',
			'webdav_user_static_tokens' => 'wdust_user_id'
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getPrimaryKey(): string {
		return 'user_id';
	}

	/**
	 * @inheritDoc
	 */
	public function getStringIdentifier( array $row ): string {
		return "User < {$row['user_name']} ({$row['user_email']}, user_id = {$row['user_id']}) >";
	}

	/**
	 * @inheritDoc
	 */
	public function syncRow( IDatabase $db, array $row ): int {
		unset( $row['user_id'] );

		$db->insert(
			'user',
			$row,
			__METHOD__
		);

		return $db->insertId();
	}

	/**
	 * @inheritDoc
	 */
	public function existsInDb( IDatabase $db, array $row ): bool {
		$userId = $db->selectField(
			'user',
			'user_id',
			[
				'user_name' => $row['user_name']
			],
			__METHOD__
		);

		return (bool)$userId;
	}

	/**
	 * @inheritDoc
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int {
		return $db->selectField(
			'user',
			'user_id',
			[
				'user_name' => $row['user_name']
			],
			__METHOD__
		);
	}
}
