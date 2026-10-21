<?php

namespace BlueSpice\WikiFarm\Export\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Util\ImportExportStep;
use InvalidArgumentException;
use MediaWiki\Message\Message;

abstract class ExportStep extends ImportExportStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param string $instanceId
	 * @return InstanceEntity
	 */
	protected function getInstance( InstanceManager $instanceManager, string $instanceId ): InstanceEntity {
		$instance = $instanceManager->getStore()->getInstanceById( $instanceId );
		if ( !$instance || !$instance->isActive() ) {
			$this->logger->error( "Cannot retrieve instance object or not active", [
				'instanceId' => $this->getInstanceId(),
				'status' => $instance?->getStatus() ?? 'not found'
			] );
			throw new InvalidArgumentException(
				Message::newFromKey( 'wikifarm-export-error-instance-not-exportable' )->text()
			);
		}
		return $instance;
	}

	/**
	 * @return string
	 */
	protected function getExportId(): string {
		return $this->data['exportId'];
	}

	/**
	 * @return string
	 */
	protected function getExportDirectory(): string {
		return $this->data['dirPath'];
	}

	/**
	 * @return array
	 */
	protected function getManifestFiles(): array {
		return $this->data['manifestFiles'] ?? [];
	}

	/**
	 * @return string
	 */
	protected function getInstanceId(): string {
		return $this->data['instanceId'];
	}

	/**
	 * @return string
	 */
	protected function getMainChecksum(): string {
		return $this->data['mainChecksum'] ?? '';
	}

	/**
	 * @param string $file
	 * @param string|null $path
	 * @return void
	 */
	protected function addFileToManifest( string $file, ?string $path = null ): void {
		$manifestFiles = $this->data['manifestFiles'] ?? [];
		$manifestFiles[] = [
			'file' => $file,
			'path' => $path ? $this->makePathRelative( $path ) : '',
		];
		$this->addToData( 'manifestFiles', $manifestFiles );
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	protected function addManifestInfo( string $key, mixed $value ): void {
		$manifestInfo = $this->data['manifestInfo'] ?? [];
		$manifestInfo[$key] = $value;
		$this->addToData( 'manifestInfo', $manifestInfo );
	}

	/**
	 * @return array
	 */
	protected function getManifestInfo(): array {
		return $this->data['manifestInfo'] ?? [];
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function makePathRelative( string $path ): string {
		$path = str_replace( $this->getExportDirectory(), '', $path );
		return trim( $path, '/' );
	}

}
