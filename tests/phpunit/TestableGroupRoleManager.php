<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\GroupRoleManager;
use MediaWiki\Permissions\Authority;

/**
 * Testable subclass that overrides logToSpecialLog to avoid ManualLogEntry dependency
 */
class TestableGroupRoleManager extends GroupRoleManager {

	/** @var array */
	public array $logCalls = [];

	/** @inheritDoc */
	protected function logToSpecialLog( string $action, Authority $actor, array $params ): void {
		$this->logCalls[] = [ 'action' => $action, 'params' => $params ];
	}
}
