<?php

namespace BlueSpice\WikiFarm\ProcessQueue;

use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessQueue\RedisQueue;

class FarmRedisProcessQueue extends RedisQueue {

	/** @var bool */
	private bool $isRoot;

	/** @var string|null */
	private ?string $forInstance;

	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
		$this->isRoot = $params['isRoot'] ?? false;
		$this->forInstance = $params['forInstance'] ?? null;
	}

	/**
	 * @param ManagedProcess $process
	 * @param array $data
	 * @return string|null
	 */
	public function enqueueProcess( ManagedProcess $process, array $data ): ?string {
		if ( $this->isRoot || $this->forInstance === 'w' ) {
			return parent::enqueueProcess( $process, $data );
		}
		$additionalArgs = $process->getAdditionalArgs();
		if ( $additionalArgs === null ) {
			$additionalArgs = [];
		}
		$additionalArgs['sfr'] = $this->forInstance;
		$additionalArgs['farm-quiet'] = true;
		$process->setAdditionalArgs( $additionalArgs );

		return parent::enqueueProcess( $process, $data );
	}

	/**
	 * @return string Shared global key prefix
	 */
	protected function getKeyPrefix(): string {
		return 'global:processmanager';
	}
}
