<?php

namespace BlueSpice\WikiFarm\Data\FavouriteInstances;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\PrimaryDataProvider as WikiInstancesPrimaryDataProvider;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\SystemInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;
use MediaWiki\User\User;

class PrimaryDataProvider extends WikiInstancesPrimaryDataProvider {

	/** @var array */
	private $favourites = [];

	/** @var IContextSource */
	private $context;

	/**
	 *
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 * @param IContextSource $context
	 * @param IAccessStore $accessStore
	 * @param UserOptionsLookup $userOptionsLookup
	 */
	public function __construct(
		InstanceStore $instanceStore, Config $farmConfig, Config $mainConfig,
		IContextSource $context, private readonly IAccessStore $accessStore,
		private readonly UserOptionsLookup $userOptionsLookup
	) {
		parent::__construct( $instanceStore, $farmConfig, $mainConfig, $context );
		$this->context = $context;
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		if ( !$this->accessStore instanceof GroupAccessStore ) {
			return [];
		}
		$user = $this->context->getUser();
		$this->favourites = $this->getFavouriteWikisForCurrentUser( $user );
		$paths = $this->accessStore->getInstancePathsWhereUserHasRole( $user, 'reader' );
		foreach ( $paths as $path ) {
			$instance = $this->instanceStore->getInstanceByPath( $path );
			if ( !$instance ) {
				continue;
			}

			if ( !$params->getQuery() || $this->queryMatches( $params->getQuery(), $instance ) ) {
				$this->appendToData( $instance );
			}
		}

		return $this->data;
	}

	/**
	 *
	 * @param InstanceEntity|null $instance
	 */
	protected function appendToData( ?InstanceEntity $instance ) {
		if (
			!$instance ||
			$instance->getStatus() === InstanceEntity::STATUS_ARCHIVED
		) {
			return;
		}

		$isFavourite = false;
		if ( in_array( $instance->getPath(), $this->favourites ) ) {
			$isFavourite = true;
		}

		$server = $this->mainConfig->get( 'Server' );
		$scriptPath = $instance->getScriptPath( $this->farmConfig );
		$fullUrl = $server . $scriptPath;
		$data = [
			Record::PATH => $instance->getPath(),
			Record::MTIME => $this->formatTimestamp( $instance->getUpdated() ),
			Record::CTIME => $this->formatTimestamp( $instance->getCreated() ),
			Record::TITLE => $instance->getDisplayName(),
			Record::FULLURL => $fullUrl,
			Record::IS_COMPLETE => $instance->getStatus() !== InstanceEntity::STATUS_INIT &&
				$instance->getStatus() !== InstanceEntity::STATUS_INSTALLED,
			Record::SUSPENDED => $instance->getStatus() === InstanceEntity::STATUS_SUSPENDED,
			Record::NOTSEARCHABLE => $instance->getMetadata()['notsearchable'] ?? false,
			Record::META_GROUP => '',
			Record::IS_SYSTEM => $instance instanceof SystemInstanceEntity,
			Record::INSTANCE_COLOR => $instance->getMetadata()['instanceColor'] ?? null,
			Record::FAVOURITE => $isFavourite,
		];

		$data['meta_keywords'] = [];
		$data['meta_group'] = '';
		$data['meta_desc'] = '';
		foreach ( $instance->getMetadata() as $key => $value ) {
			if ( $key === 'notsearchable' ) {
				continue;
			}
			$data['meta_' . $key] = $value;
		}

		$this->data[] = new Record( (object)$data );
	}

	/**
	 * @param User $user
	 * @return array
	 */
	private function getFavouriteWikisForCurrentUser( $user ): array {
		$favouriteOptions = $this->userOptionsLookup->getOption( $user, 'bs-farm-instances-favorite' );
		if ( !$favouriteOptions ) {
			return [];
		}
		$favourites = explode( ',', $favouriteOptions );
		return $favourites;
	}

}
