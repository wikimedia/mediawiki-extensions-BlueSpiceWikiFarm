<?php

namespace BlueSpice\WikiFarm\Rest;

use Exception;
use Wikimedia\ParamValidator\ParamValidator;

class EditInstanceHandler extends CreateInstanceHandler {

	/**
	 * @param string $instanceName
	 * @param string $displayName
	 * @param array $options
	 * @return array
	 * @throws Exception
	 */
	protected function executeAction( string $instanceName, string $displayName, array $options ): array {
		$instance = $this->getInstanceEntity( $instanceName );
		$instance->setDisplayName( $displayName );
		$instance->setMetadata( $options['metadata'] ?? [] );
		$instance->setConfigItem( 'wgLanguageCode', $options['lang'] );
		$this->getInstanceManager()->getStore()->store( $instance );

		return [
			'success' => true
		];
	}

	/**
	 * @return array
	 */
	public function getBodyParamSettings(): array {
		return [
			'displayName' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_REQUIRED => true,
			],
			'description' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'language' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_REQUIRED => false,
			],
			'metadata' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_TYPE => 'array',
			]
		];
	}

	/**
	 * @return string[]
	 */
	protected function getRequiredPermissions(): array {
		return [ 'wikifarm-managewiki' ];
	}
}
