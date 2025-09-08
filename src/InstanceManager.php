<?php

namespace BlueSpice\WikiFarm;

use BlueSpice\WikiFarm\Process\ArchiveInstance;
use BlueSpice\WikiFarm\Process\CloneInstance;
use BlueSpice\WikiFarm\Process\CreateInstance;
use BlueSpice\WikiFarm\Process\PurgeInstance;
use Exception;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\DatabaseFactory;
use Wikimedia\Rdbms\IDatabase;

class InstanceManager {
	/** @var InstanceStore */
	private $instanceStore;

	/** @var ProcessManager */
	private $processManager;

	/** @var LoggerInterface */
	private $logger;

	/** @var Config */
	private $farmConfig;

	/** @var Config */
	private $mainConfig;

	/** @var DatabaseFactory */
	private $databaseFactory;

	/** @var InstanceCountLimiter */
	private $countLimiter;

	/** @var InstancePathGenerator */
	private $pathGenerator;

	/**
	 * @param InstanceStore $instanceStore
	 * @param ProcessManager $processManager
	 * @param LoggerInterface $logger
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 * @param DatabaseFactory $databaseFactory
	 * @param InstanceCountLimiter $countLimiter
	 * @param InstancePathGenerator $pathGenerator
	 */
	public function __construct(
		InstanceStore $instanceStore, ProcessManager $processManager, LoggerInterface $logger,
		Config $farmConfig, Config $mainConfig, DatabaseFactory $databaseFactory,
		InstanceCountLimiter $countLimiter, InstancePathGenerator $pathGenerator
	) {
		$this->instanceStore = $instanceStore;
		$this->processManager = $processManager;
		$this->logger = $logger;
		$this->farmConfig = $farmConfig;
		$this->mainConfig = $mainConfig;
		$this->databaseFactory = $databaseFactory;
		$this->countLimiter = $countLimiter;
		$this->pathGenerator = $pathGenerator;
	}

	/**
	 * @param string $path
	 * @param string $displayName
	 * @param array $options
	 * @return string PID for the process started
	 * @throws Exception
	 */
	public function createInstance( string $path, string $displayName, array $options = [] ): string {
		$instance = $this->createEmptyInstance( $path, $displayName, $options );
		$options['instanceId'] = $instance->getId();
		return $this->startProcess( new CreateInstance( $options ), $instance->getId() );
	}

	/**
	 * @param string $path
	 * @param InstanceEntity $sourceInstance
	 * @param string $displayName
	 * @param array $options
	 * @return string PID for the process started
	 * @throws Exception
	 */
	public function cloneInstance(
		string $path, InstanceEntity $sourceInstance, string $displayName, array $options = []
	): string {
		if ( $sourceInstance->getStatus() !== InstanceEntity::STATUS_READY ) {
			$this->throwException( InvalidArgumentException::class, 'wikifarm-error-source-instance-not-ready' );
		}
		$instance = $this->createEmptyInstance( $path, $displayName, $options );
		$options['instanceId'] = $instance->getId();
		$options['sourceInstanceId'] = $sourceInstance->getId();
		return $this->startProcess( new CloneInstance( $options ), $instance->getId() );
	}

	/**
	 * @param InstanceEntity $instanceEntity
	 * @param string|null $message
	 * @return void
	 */
	public function putInstanceInMaintenanceMode( InstanceEntity $instanceEntity, ?string $message = null ) {
		$instanceEntity->setStatus( InstanceEntity::STATUS_MAINTENANCE );
		if ( $message ) {
			$instanceEntity->setConfigItem( 'wgWikiFarmConfig_maintenanceMessage', $message );
		}
		$this->instanceStore->store( $instanceEntity );
	}

	/**
	 * @param InstanceEntity $instanceEntity
	 * @return void
	 */
	public function clearMaintenanceMode( InstanceEntity $instanceEntity ) {
		$instanceEntity->setStatus( InstanceEntity::STATUS_READY );
		$instanceEntity->removeConfigItem( 'wgWikiFarmConfig_maintenanceMessage' );
		$this->instanceStore->store( $instanceEntity );
	}

	/**
	 * @param string $path
	 * @return string
	 * @throws Exception
	 */
	public function getUrlForNewInstance( string $path ): string {
		$base = $this->farmConfig->get( 'basePath' );
		$base = trim( $base, '/' );
		$scriptPath = $base . '/' . $path;
		return $this->mainConfig->get( 'Server' ) . $scriptPath;
	}

	/**
	 * @param string $path
	 * @param string $displayName
	 * @param array $options
	 * @return InstanceEntity
	 * @throws Exception
	 */
	public function createEmptyInstance( string $path, string $displayName, array $options = [] ): InstanceEntity {
		$this->assertCreatable( $path );
		// Create empty instance shell
		$instance = $this->instanceStore->newEmptyInstance( $path, $this->getFarmConfig() );
		$instance->setDisplayName( $displayName );
		if ( isset( $options['lang'] ) ) {
			// Not the nicest that this is hardcoded here
			$instance->setConfigItem( 'wgLanguageCode', $options['lang'] );
		}
		$instance->setMetadata( $options['metadata'] ?? [] );
		if ( isset( $options['config'] ) ) {
			foreach ( $options['config'] as $key => $value ) {
				$instance->setConfigItem( $key, $value );
			}
		}
		$this->instanceStore->store( $instance );
		return $instance;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string
	 * @throws Exception
	 */
	public function archiveInstance( InstanceEntity $instance ): string {
		return $this->startProcess( new ArchiveInstance( [ 'instanceId' => $instance->getId() ] ), $instance->getId() );
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string
	 * @throws Exception
	 */
	public function purgeInstance( InstanceEntity $instance ): string {
		return $this->startProcess( new PurgeInstance( [ 'instanceId' => $instance->getId() ] ), $instance->getId() );
	}

	/**
	 * @param ManagedProcess $process
	 * @param string $id Instance id
	 * @return string
	 * @throws Exception
	 */
	private function startProcess( ManagedProcess $process, string $id ): string {
		$pid = $this->processManager->startProcess( $process );
		if ( !$pid ) {
			$this->logger->error( 'Failed to start process of type ' . get_class( $process ) );
			$this->throwException( RuntimeException::class, 'wikifarm-error-failed-to-start-process' );
		}
		$this->instanceStore->storeRunningProcess( $id, $pid, get_class( $process ) );
		$this->logger->info( 'Started process of type ' . get_class( $process ) . ' with pid ' . $pid );
		return $pid;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return IDatabase|null
	 */
	public function getDatabaseConnectionForInstance( InstanceEntity $instance ): ?IDatabase {
		$params = [
			'host' => $this->mainConfig->get( 'DBserver' ),
			'user' => $this->mainConfig->get( 'DBuser' ),
			'password' => $this->mainConfig->get( 'DBpassword' ),
			'tablePrefix' => $instance->getDBPrefix(),
			'dbname' => $instance->getDBName()
		];

		try {
			return $this->databaseFactory->create( $this->mainConfig->get( 'DBtype' ), $params );
		} catch ( \Throwable $ex ) {
			return null;
		}
	}

	/**
	 * @param string $path
	 * @return void
	 * @throws Exception
	 */
	public function assertCreatable( string $path ) {
		if ( !$this->countLimiter->canCreate() ) {
			$this->throwException( InvalidArgumentException::class, 'wikifarm-error-instance-limit-reached' );
		}
		$this->assertValidPath( $path );
	}

	/**
	 * @param string $path
	 * @return void
	 * @throws Exception
	 */
	private function assertValidPath( string $path ) {
		if ( !$this->pathGenerator->checkIfValid( $path, true ) ) {
			$this->throwException( InvalidArgumentException::class, 'wikifarm-error-invalid-path' );
		}
	}

	/**
	 * @param string $class
	 * @param string $msgKey
	 * @throws Exception
	 */
	private function throwException( string $class, string $msgKey ) {
		throw new $class( Message::newFromKey( $msgKey )->plain() );
	}

	/**
	 * @return InstanceStore
	 */
	public function getStore(): InstanceStore {
		return $this->instanceStore;
	}

	/**
	 * @return LoggerInterface
	 */
	public function getLogger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * @return Config
	 */
	public function getFarmConfig(): Config {
		return $this->farmConfig;
	}

	/**
	 * @param InstanceEntity $instanceEntity
	 * @return void
	 */
	public function removeInstance( InstanceEntity $instanceEntity ) {
		$this->instanceStore->removeEntry( $instanceEntity );
	}

}
