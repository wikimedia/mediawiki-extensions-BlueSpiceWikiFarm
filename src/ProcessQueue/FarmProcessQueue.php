<?php

namespace BlueSpice\WikiFarm\ProcessQueue;

use MediaWiki\Config\Config;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessQueue\SimpleDatabaseQueue;
use Wikimedia\Rdbms\DatabaseFactory;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class FarmProcessQueue extends SimpleDatabaseQueue {

	/**
	 * @var string
	 */
	private $forInstance;

	/**
	 * @var Config
	 */
	private $farmConfig;

	/**
	 * @var bool
	 */
	private $isRoot;

	/**
	 * @param ILoadBalancer $lb
	 * @param Config $farmConfig
	 * @param string $instance
	 * @param bool $isRoot
	 */
	public function __construct( ILoadBalancer $lb, Config $farmConfig, string $instance, bool $isRoot ) {
		parent::__construct( $lb );
		$this->farmConfig = $farmConfig;
		$this->forInstance = $instance;
		$this->isRoot = $isRoot;
	}

	/**
	 * @return IDatabase
	 */
	protected function getDB(): IDatabase {
		return ( new DatabaseFactory() )->create(
			$this->farmConfig->get( 'managementDBtype' ), [
				'host' => $this->farmConfig->get( 'managementDBserver' ),
				'user' => $this->farmConfig->get( 'managementDBuser' ),
				'password' => $this->farmConfig->get( 'managementDBpassword' ),
				'tablePrefix' => $this->farmConfig->get( 'managementDBprefix' ),
				'dbname' => $this->farmConfig->get( 'managementDBname' ),
			]
		);
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
