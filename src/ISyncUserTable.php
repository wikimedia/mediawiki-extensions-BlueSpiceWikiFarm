<?php

namespace BlueSpice\WikiFarm;

use Wikimedia\Rdbms\IDatabase;

/**
 * Each class which is used to "sync" specific user table between several wiki instances -
 * - should implement this interface.
 * @see \SyncUsers
 */
interface ISyncUserTable {

	/**
	 * Gets array with data about all related tables.
	 * Key - table name, value - name of the column where primary key of current table is used.
	 *
	 * @return array
	 */
	public function getRelatedTables(): array;

	/**
	 * Gets name of primary key column.
	 * Complex primary keys (consisting of few columns) are not used in user tables, so just string here.
	 *
	 * @return string
	 */
	public function getPrimaryKey(): string;

	/**
	 * Gets human-readable string which can be used to identify specific record.
	 * For example, in case with user it can be "<user_name> (<user_email>)" or "<user_name>".
	 * For actor, it can be "<actor_name"> (<actor_user>)", etc.
	 *
	 * Used for logging/debugging purposes.
	 *
	 * @param array $row Row with data to make string ID
	 * @return string
	 */
	public function getStringIdentifier( array $row ): string;

	/**
	 * Imports entity specified by data row to the "shared DB".
	 *
	 * Returns primary key (ID) of imported row. It can be either "user_id" (for "user" table) value or
	 * "actor_id" (for "actor" table).
	 * Only one integer must be returned, which can identify imported row.
	 * Also, it makes sense only for tables with {@link self::getRelatedTables()} returning not empty array.
	 * In that case returned integer will be used later to fix foreign keys in related tables.
	 *
	 * If table uses complex primary key (example - "user_groups", "user_properties) - just <tt>0</tt> can be returned.
	 *
	 * Attention!
	 * "shared DB" must already be selected before wards with this method {@link Database::selectDomain()}
	 *
	 * @param IDatabase $db
	 * @param array $row
	 * @return int
	 */
	public function syncRow( IDatabase $db, array $row ): int;

	/**
	 * Checks if that entity already exists in "shared DB".
	 *
	 * Attention!
	 * "shared DB" must already be selected before wards with this method {@link Database::selectDomain()}
	 *
	 * @param IDatabase $db
	 * @param array $row
	 * @return bool
	 */
	public function existsInDb( IDatabase $db, array $row ): bool;

	/**
	 * Returns primary key (ID) of existing row.
	 * It can be either "user_id" (for "user" table) value or
	 * "actor_id" (for "actor" table).
	 * Only one integer must be returned, which can identify existing row.
	 * Also, it makes sense only for tables with {@link self::getRelatedTables()} returning not empty array.
	 * In that case returned integer will be used later to fix foreign keys in related tables.
	 *
	 * If table uses complex primary key (example - "user_groups", "user_properties) - just <tt>0</tt> can be returned.
	 *
	 * This method is used only if {@link self::existsInDb()} returned <tt>true</tt>, so we can assume
	 * that this row exists.
	 *
	 * @param IDatabase $db
	 * @param array $row
	 * @return int
	 */
	public function getExistingRowId( IDatabase $db, array $row ): int;
}
