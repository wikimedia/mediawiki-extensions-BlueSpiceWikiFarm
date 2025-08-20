<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Preferences\Hook\GetPreferencesHook;

class AddUserPreference implements GetPreferencesHook {

	/**
	 *
	 * @inheritDoc
	 */
	public function onGetPreferences( $user, &$preferences ) {
		$api = [ 'type' => 'api' ];
		$preferences[ 'bs-farm-instances-favorite' ] = $api;
	}
}
