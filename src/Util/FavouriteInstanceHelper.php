<?php

namespace BlueSpice\WikiFarm\Util;

use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserOptionsLookup;
use Wikimedia\Rdbms\IDBAccessObject;

class FavouriteInstanceHelper {

	/**
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct( private readonly UserOptionsLookup $userOptionsLookup ) {
	}

	/**
	 * Return list of paths that user marked as favourite
	 *
	 * @param UserIdentity $user
	 * @return array
	 */
	public function getFavouriteInstancesForUser( UserIdentity $user ): array {
		$favouriteOptions = $this->userOptionsLookup->getOption(
			$user, 'bs-farm-instances-favorite', null, false, IDBAccessObject::READ_LATEST
		);
		if ( !$favouriteOptions ) {
			return [];
		}
		$favourites = explode( ',', $favouriteOptions );
		return array_map(
			static fn ( $item ) => trim( $item ),
			$favourites
		);
	}
}
