<?php

namespace BlueSpice\WikiFarm\Rest;

use MediaWiki\Rest\Response;
use Wikimedia\ParamValidator\ParamValidator;

class PinInstanceHandler extends InstanceHandler {

	/**
	 * @param string $instanceName
	 * @param array $bodyParams
	 * @return Response
	 * @throws \MediaWiki\Rest\HttpException
	 */
	protected function doExecute( string $instanceName, array $bodyParams ): Response {
		$instance = $this->getInstanceEntity( $instanceName );
		$instance->setPinned( $bodyParams['pinned'] );
		$this->getInstanceManager()->getStore()->store( $instance );

		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	/**
	 * @return array
	 */
	public function getBodyParamSettings(): array {
		return [
			'pinned' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'boolean',
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
