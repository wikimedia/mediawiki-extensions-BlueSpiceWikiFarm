<?php

namespace BlueSpice\WikiFarm\Rest;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;

class DeleteInstanceHandler extends InstanceHandler {

	/**
	 * @param string $instanceName
	 * @param array $bodyParams
	 * @return Response
	 * @throws HttpException
	 */
	protected function doExecute( string $instanceName, array $bodyParams ): Response {
		$instance = $this->getInstanceEntity( $instanceName );
		if ( !$instance->isComplete() ) {
			$pid = $this->getInstanceManager()->purgeInstance( $instance );
		} else {
			$pid = $this->getInstanceManager()->archiveInstance( $instance );
		}
		return $this->getResponseFactory()->createFromReturnValue( $pid );
	}

	/**
	 * @return string[]
	 */
	protected function getRequiredPermissions(): array {
		return [ 'wikifarm-deletewiki' ];
	}

	/**
	 * @return array
	 */
	protected function getJSONBodyParamSettings(): array {
		return [];
	}
}
