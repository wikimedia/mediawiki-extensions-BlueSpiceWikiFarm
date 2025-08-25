<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Rest\Response;

class SuspendHandler extends InstanceHandler {

	/**
	 * @return string[]
	 */
	protected function getRequiredPermissions(): array {
		return [ 'wikifarm-managewiki' ];
	}

	/**
	 * @param string $instanceName
	 * @param array $bodyParams
	 * @return Response
	 * @throws \MediaWiki\Rest\HttpException
	 */
	protected function doExecute( string $instanceName, array $bodyParams ): Response {
		$instance = $this->getInstanceEntity( $instanceName );
		$instance->setStatus( $this->getStatusToSet() );
		$this->getInstanceManager()->getStore()->store( $instance );

		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	/**
	 * @return string
	 */
	protected function getStatusToSet(): string {
		return InstanceEntity::STATUS_SUSPENDED;
	}

	/**
	 * @return array
	 */
	protected function getJSONBodyParamSettings(): array {
		return [];
	}
}
