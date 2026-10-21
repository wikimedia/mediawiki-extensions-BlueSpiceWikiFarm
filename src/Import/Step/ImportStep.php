<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Util\ImportExportStep;
use InvalidArgumentException;
use MediaWiki\Message\Message;

abstract class ImportStep extends ImportExportStep {

	/**
	 * @return array
	 */
	protected function getInstanceData(): array {
		return $this->data['manifest']['instanceData'] ?? [];
	}

	/**
	 * @return string|null
	 */
	protected function getDatabaseSchemaFile(): ?string {
		return $this->data['manifest']['databaseSchemaFile'] ?? null;
	}

	/**
	 * @return string|null
	 */
	protected function getDatabaseDataFile(): ?string {
		return $this->data['manifest']['databaseDataFile'] ?? null;
	}

	/**
	 * @return string|null
	 */
	protected function getImportPackagePath(): ?string {
		return $this->data['importPackagePath'] ?? null;
	}

	/**
	 * @return string|null
	 */
	protected function getImagesDirectory(): ?string {
		return $this->data['manifest']['imagesExportDirectory'] ?? null;
	}

	/**
	 * @return string|null
	 */
	protected function getInstanceId(): ?string {
		return $this->data['createdInstanceId'] ?? null;
	}

	/**
	 * @param InstanceManager $instanceManager
	 * @return InstanceEntity
	 */
	protected function getInstance( InstanceManager $instanceManager ): InstanceEntity {
		$instance = $instanceManager->getStore()->getInstanceById( $this->getInstanceId() );
		if ( !$instance ) {
			$this->logger->error( "Cannot retrieve instance object, even though it should be present", [
				'instanceId' => $this->getInstanceId(),
			] );
			throw new InvalidArgumentException(
				Message::newFromKey( 'wikifarm-import-error-instance-not-found' )->text()
			);
		}
		return $instance;
	}
}
