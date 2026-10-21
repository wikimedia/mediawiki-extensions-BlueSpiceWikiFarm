<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use DateTime;
use InvalidArgumentException;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use Throwable;

class CreateInstance extends ImportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	protected function runStep(): void {
		$instanceData = $this->getInstanceData();

		$mustHaveKeys = [ 'name', 'path', 'dbname', 'dbprefix' ];
		foreach ( $mustHaveKeys as $key ) {
			if ( !isset( $instanceData[$key] ) ) {
				$this->logger->error( "Instance data is missing key \"{key}\"", [ 'key' => $key ] );
				throw new InvalidArgumentException(
					Message::newFromKey( 'wikifarm-import-invalid-instance-data' )->text()
				);
			}
		}

		if ( $this->instanceManager->getStore()->getInstanceByPath( $instanceData['path'] ) ) {
			$this->logger->error( 'Instance already exists', [ 'path' => $instanceData['path'] ] );
			throw new InvalidArgumentException(
				Message::newFromKey( 'wikifarm-import-instance-already-exists' )->text()
			);
		}

		$newInstanceDB = $this->instanceManager->getDatabaseConnectionForRawParams(
			$instanceData['dbname'], $instanceData['dbprefix']
		);
		if ( $newInstanceDB && $newInstanceDB->tableExists( 'page' ) ) {
			$this->logger->error( 'Instance database already exists', [
				'dbname' => $instanceData['dbname'],
				'dbprefix' => $instanceData['dbprefix'],
			] );
			throw new InvalidArgumentException(
				Message::newFromKey( 'wikifarm-import-instance-db-already-exists' )->text()
			);
		}

		try {
			$instanceId = $this->instanceManager->getStore()->generateId();
			$instance = new InstanceEntity(
				id: $instanceId,
				path: $instanceData['path'],
				displayName: $instanceData['name'],
				created: new DateTime(),
				updated: new DateTime(),
				status: InstanceEntity::STATUS_INIT,
				dbName: $instanceData['dbname'],
				dbPrefix: $instanceData['dbprefix'],
				metadata: $instanceData['metadata'] ?? [],
				config: $instanceData['config'] ?? [],
			);

			$this->instanceManager->getStore()->store( $instance );
		} catch ( Throwable $e ) {
			$this->logger->error( 'Failed to create instance', [ 'error' => $e->getMessage() ] );
			throw new \RuntimeException( Message::newFromKey( 'wikifarm-import-instance-creation-failed' )->text() );
		}

		$this->addToData( 'createdInstanceId', $instanceId );
	}
}
