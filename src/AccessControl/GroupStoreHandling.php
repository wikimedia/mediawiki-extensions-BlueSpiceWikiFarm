<?php

namespace BlueSpice\WikiFarm\AccessControl;

use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\CommonWebAPIs\Hook\MWStakeGroupStoreGroupDisplayNameHook;
use MWStake\MediaWiki\Component\CommonWebAPIs\Hook\MWStakeGroupStoreGroupTypeFilterHook;

class GroupStoreHandling implements
	MWStakeGroupStoreGroupTypeFilterHook,
	MWStakeGroupStoreGroupDisplayNameHook
{

	/**
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly InstanceGroupCreator $instanceGroupCreator,
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
		// Only show custom groups (hide implicit wiki instance groups)
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
