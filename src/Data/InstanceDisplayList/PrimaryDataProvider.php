<?php

namespace BlueSpice\WikiFarm\Data\InstanceDisplayList;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\Data\WikiInstances\PrimaryDataProvider as WikiInstancesPrimaryDataProvider;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Util\FavouriteInstanceHelper;
use BlueSpice\WikiFarm\Util\InstanceDisplayRecordHelper;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MediaWiki\User\Options\UserOptionsLookup;
use MWStake\MediaWiki\Component\DataStore\Filter\Boolean;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider extends WikiInstancesPrimaryDataProvider {

	/** @var array */
	private $favourites = [];

	/** @var IContextSource */
	private $context;

	/**
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
		private readonly UserOptionsLookup $userOptionsLookup,
		private readonly InstanceDisplayRecordHelper $instanceDisplayRecordHelper
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
		$favouriteHelper = new FavouriteInstanceHelper( $this->userOptionsLookup );
		$this->favourites = $favouriteHelper->getFavouriteInstancesForUser( $user );
		$paths = $this->accessStore->getInstancePathsWhereUserHasRole( $user, 'reader' );
		if ( empty( $paths ) ) {
			return [];
		}
		if ( $this->onlyPinned( $params ) ) {
			$pinned = $this->instanceStore->getPinnedInstances();
			$instances = array_filter( $pinned, static function ( $instance ) use ( $paths ) {
				return in_array( $instance->getPath(), $paths );
			} );
		} else {
			if ( $this->onlyFavourites( $params ) ) {
				$paths = array_intersect( $paths, $this->favourites );
			}
			$instances = $this->instanceStore->getMultiple( 'sfi_path', $paths );
		}

		foreach ( $instances as $instance ) {
			if ( !$params->getQuery() || $this->queryMatches( $params->getQuery(), $instance ) ) {
				$this->appendToData( $instance );
			}
		}

		return $this->data;
	}

	/**
	 * @param InstanceEntity|null $instance
	 */
	protected function appendToData( ?InstanceEntity $instance ) {
		if ( !$instance ) {
			return;
		}
		$record = $this->instanceDisplayRecordHelper->getDisplayRecord( $instance, $this->context->getUser() );
		if ( $record ) {
			$this->data[] = $record;
		}
	}

	/**
	 * @param ReaderParams $params
	 * @return bool
	 */
	private function onlyPinned( ReaderParams $params ): bool {
		return $this->isBooleanTrue( InstanceDisplayRecord::PINNED, $params );
	}

	/**
	 * @param ReaderParams $params
	 * @return bool
	 */
	private function onlyFavourites( ReaderParams $params ): bool {
		return $this->isBooleanTrue( InstanceDisplayRecord::FAVOURITE, $params );
	}

	/**
	 * @param string $field
	 * @param ReaderParams $params
	 * @return bool
	 */
	public function isBooleanTrue( string $field, ReaderParams $params ): bool {
		foreach ( $params->getFilter() as $filter ) {
			if ( $filter->getField() !== $field ) {
				continue;
			}

			$filter->setApplied();
			if ( !( $filter instanceof Boolean ) ) {
				return false;
			}

			return (bool)$filter->getValue();
		}

		return false;
	}
}
