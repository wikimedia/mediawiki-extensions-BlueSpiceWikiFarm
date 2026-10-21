<?php

namespace BlueSpice\WikiFarm\Export;

interface BlueSpiceWikiFarmExportDatabaseDataSkipTablesHook {

	/**
	 * @param array &$skipTables
	 * @return void
	 */
	public function onBlueSpiceWikiFarmExportDatabaseDataSkipTables( &$skipTables ): void;
}
