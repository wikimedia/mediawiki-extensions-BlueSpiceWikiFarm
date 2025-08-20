<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Cache\Hook\MessageCacheFetchOverridesHook;
use MediaWiki\Config\Config;

class SetupGroupMessages implements MessageCacheFetchOverridesHook {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct( private readonly Config $farmConfig ) {
	}

	/**
	 * @param array &$keys
	 * @return void
	 */
	public function onMessageCacheFetchOverrides( array &$keys ): void {
		if ( !$this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			return;
		}
		// Issue is: Since we have all those dynamic groups, those names will be shown
		// in permission error message. That is not so nice.
		// This makes situation only somewhat better, by not specifying the group names
		// Ideally, it should format group names
		$keys['badaccess-groups'] = 'badaccess-group0';
	}
}
