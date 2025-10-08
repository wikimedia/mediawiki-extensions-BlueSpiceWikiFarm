<?php

namespace BlueSpice\WikiFarm\ProcessQueue;

use MediaWiki\Config\Config;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessQueue\SimpleDatabaseQueue;
use Wikimedia\Rdbms\ILoadBalancer;

class FarmProcessQueue extends SimpleDatabaseQueue {

	use GlobalProcessDatabaseTrait;

	/**
	 * @param ILoadBalancer $lb
	 * @param Config $farmConfig
	 * @param string $forInstance
	 * @param bool $isRoot
	 */
	public function __construct(
		ILoadBalancer $lb,
		protected readonly Config $farmConfig,
		private readonly string $forInstance,
		private readonly bool $isRoot
	) {
		parent::__construct( $lb );
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
}
