<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceEntity;
use Exception;
use Wikimedia\ParamValidator\ParamValidator;

class CloneInstanceHandler extends CreateInstanceHandler {

	/**
	 * @param string $instanceName
	 * @param string $displayName
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	protected function executeAction( string $instanceName, string $displayName, array $options ): array {
		$sourceInstance = $this->getSourceInstance();
		$pid = $this->getInstanceManager()->cloneInstance( $instanceName, $sourceInstance, $displayName, $options );
		if ( $pid ) {
			return [
				'process' => $pid,
				'instanceUrl' => $this->getInstanceManager()->getUrlForNewInstance( $instanceName )
			];
		}
		throw new Exception( 'Failed to start cloning process' );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return parent::getParamSettings() + [
			'sourceInstancePath' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	/**
	 * @return InstanceEntity
	 * @throws Exception
	 */
	private function getSourceInstance(): InstanceEntity {
		$validated = $this->getValidatedParams();
		$sourceInstancePath = $validated['sourceInstancePath'];
		$sourceInstance = $this->getInstanceManager()->getStore()->getInstanceByPath( $sourceInstancePath );
		if ( !$sourceInstance ) {
			throw new Exception( 'Source instance not found' );
		}
		return $sourceInstance;
	}
}
