<?php

namespace BlueSpice\WikiFarm\AccessControl;

use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\CommonWebAPIs\Hook\MWStakeGroupStoreGroupDisplayNameHook;
use MWStake\MediaWiki\Component\CommonWebAPIs\Hook\MWStakeGroupStoreGroupTypeFilterHook;

class TeamGroupStoreHandling implements
	MWStakeGroupStoreGroupTypeFilterHook,
	MWStakeGroupStoreGroupDisplayNameHook
{

	/**
	 * @param TeamManager $teamManager
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly TeamManager $teamManager,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @param array &$types
	 * @return void
	 */
	public function onMWStakeGroupStoreGroupTypeFilter( array &$types ) {
		if ( $this->shouldSkip() ) {
			return;
		}
		// Only show wiki-team groups
		$types = [ 'custom' ];
	}

	public function onMWStakeGroupStoreGroupDisplayName(
		string $groupName, string &$displayName, string $groupType
	): void {
		if ( $this->shouldSkip() ) {
			return;
		}
		if ( $groupType === 'implicit' && $groupName === 'user' ) {
			$displayName = Message::newFromKey( 'wikifarm-access-group-name-user' )->text();
			return;
		}
		if ( $groupType !== 'custom' ) {
			return;
		}
		$prefix = $this->teamManager->getTeamPrefix();
		if ( str_starts_with( $groupName, $prefix ) ) {
			$displayName = str_replace( $prefix, '', $groupName );
		}
	}

	/**
	 * @return bool
	 */
	private function shouldSkip() {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			return false;
		}
		return true;
	}
}
