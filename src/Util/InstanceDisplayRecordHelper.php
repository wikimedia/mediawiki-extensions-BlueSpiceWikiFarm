<?php

namespace BlueSpice\WikiFarm\Util;

use BlueSpice\WikiFarm\Data\InstanceDisplayList\InstanceDisplayRecord;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\SystemInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;

class InstanceDisplayRecordHelper {

	/** @var array|null */
	private ?array $favourites = null;

	/**
	 * @param FavouriteInstanceHelper $favouriteInstanceHelper
	 * @param Config $mainConfig
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly FavouriteInstanceHelper $favouriteInstanceHelper,
		private readonly Config $mainConfig,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @param InstanceEntity $instance
	 * @param UserIdentity $forUser
	 * @return InstanceDisplayRecord|null
	 */
	public function getDisplayRecord( InstanceEntity $instance, UserIdentity $forUser ): ?InstanceDisplayRecord {
		if ( $instance->getStatus() === InstanceEntity::STATUS_ARCHIVED ) {
			return null;
		}
		$this->assertFavorites( $forUser );

		$isFavourite = false;
		if ( in_array( $instance->getPath(), $this->favourites ) ) {
			$isFavourite = true;
		}

		$server = $this->mainConfig->get( 'Server' );
		$scriptPath = $instance->getScriptPath( $this->farmConfig );
		$fullUrl = $server . $scriptPath;

		return new InstanceDisplayRecord( (object)[
			InstanceDisplayRecord::PATH => $instance->getPath(),
			InstanceDisplayRecord::PATH_LABEL => $this->getPathLabel( $instance ),
			InstanceDisplayRecord::TITLE => $instance->getDisplayName(),
			InstanceDisplayRecord::FULLURL => $fullUrl,
			InstanceDisplayRecord::INSTANCE_COLOR => $instance->getMetadata()['instanceColor']['background'] ?? null,
			InstanceDisplayRecord::INSTANCE_COLOR_LIGHT_TEXT =>
				(bool)( $instance->getMetadata()['instanceColor']['lightText'] ?? false ),
			InstanceDisplayRecord::FAVOURITE => $isFavourite,
			InstanceDisplayRecord::META_GROUP => $instance->getMetadata()['group'] ?? '',
			InstanceDisplayRecord::IS_SYSTEM => $instance instanceof SystemInstanceEntity,
			InstanceDisplayRecord::WIKI_ID => $instance->getWikiId(),
		] );
	}

	/**
	 * Same label the instance node in the breadcrumbs shows
	 * (see SharedInstancesRootNode). The root instance has no such node.
	 *
	 * @param InstanceEntity $instance
	 * @return string
	 */
	private function getPathLabel( InstanceEntity $instance ): string {
		$path = $instance->getPath();
		if ( $path === 'w' ) {
			return '';
		}
		if ( $path === $this->farmConfig->get( 'sharedResourcesWikiPath' ) ) {
			return Message::newFromKey( 'wikifarm-shared-instance-name' )->text();
		}
		return str_replace( '_', ' ', $path );
	}

	/**
	 * @param UserIdentity $forUser
	 * @return void
	 */
	private function assertFavorites( UserIdentity $forUser ): void {
		if ( $this->favourites === null ) {
			$this->favourites = $this->favouriteInstanceHelper->getFavouriteInstancesForUser( $forUser );
		}
	}

}
