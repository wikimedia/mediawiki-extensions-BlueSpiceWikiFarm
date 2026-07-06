<?php

namespace BlueSpice\WikiFarm\Hook\Integration;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\TokenAuthenticator\MWStakeTokenAuthenticatorGetAuthInfoHook;

class AddTokenAuthenticatorSearchableWikis implements MWStakeTokenAuthenticatorGetAuthInfoHook {

	public function __construct(
		private readonly IAccessStore $accessStore,
		private readonly \Config $farmConfig,
		private readonly FarmWikiMap $farmWikiMap
	) {
	}

	public function onMWStakeTokenAuthenticatorGetAuthInfo( UserIdentity $user, array &$meta ): void {
		if ( !$this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			return;
		}
		$readableInstances = $this->accessStore->getInstancePathsWhereUserHasRole( $user, 'reader' );
		$map = $this->farmWikiMap->getMap();

		$searchable = [];
		foreach ( $map as $wikiId => $instance ) {
			if ( in_array( $instance->getPath(), $readableInstances ) ) {
				$searchable[] = $wikiId;
			}
		}
		$meta['searchableInstances'] = $searchable;
	}
}
