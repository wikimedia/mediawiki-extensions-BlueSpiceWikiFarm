<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\Config;

class InstanceCountLimiter {

	/**
	 * @var Config
	 */
	private $farmConfig;

	/**
	 * @var InstanceStore
	 */
	private $instanceStore;

	/**
	 * @param Config $farmConfig
	 * @param InstanceStore $instanceStore
	 */
	public function __construct( Config $farmConfig, InstanceStore $instanceStore ) {
		$this->farmConfig = $farmConfig;
		$this->instanceStore = $instanceStore;
	}

	/**
	 * @return bool
	 */
	public function canCreate(): bool {
		return !$this->isLimited() || $this->getCurrentActiveCount() < $this->getLimit();
	}

	/**
	 * @return int
	 */
	public function getCurrentActiveCount(): int {
		$count = 0;
		foreach ( $this->instanceStore->getInstanceIds() as $id ) {
			$instance = $this->instanceStore->getInstanceById( $id );
			if ( $instance && $instance->isActive() ) {
				$count++;
			}
		}

		return $count;
	}

	/**
	 * @return bool
	 */
	public function isLimited(): bool {
		return $this->getLimit() !== null;
	}

	/**
	 * @return int|null
	 */
	public function getLimit(): ?int {
		$limit = $this->farmConfig->get( 'instanceLimit' );

		if ( !$limit || !is_int( $limit ) || $limit < 0 ) {
			return null;
		}
		return $limit;
	}
}
