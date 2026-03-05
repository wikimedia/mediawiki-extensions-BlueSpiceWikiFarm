<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MediaWiki\Permissions\Authority;

/**
 * Subclass that replaces the MW-specific special log call with a no-op,
 * allowing TeamManager to be unit-tested without a real MediaWiki database.
 */
class TestableTeamManager extends TeamManager {

	/** @var array Captured log calls for assertions */
	public array $logCalls = [];

	protected function logToSpecialLog( string $action, Authority $actor, array $params ): void {
		$this->logCalls[] = [ 'action' => $action, 'params' => $params ];
	}
}
