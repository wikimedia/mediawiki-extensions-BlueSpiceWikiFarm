<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Status\Status;

class ForeignRequestExecution {

	public function __construct(
		private readonly HttpRequestFactory $httpRequestFactory,
		private readonly \Config $farmConfig
	) {
	}

	/**
	 * @param InstanceEntity $instanceEntity
	 * @param string $method
	 * @param array $params
	 * @param string $endpoint
	 * @return Status
	 */
	public function request(
		InstanceEntity $instanceEntity, string $method, array $params = [], string $endpoint = 'api'
	): Status {
		$instanceUrl = $instanceEntity->getUrl( $this->farmConfig ) . '/' . $endpoint . '.php';
		$url = $method === 'GET' ? wfAppendQuery( $instanceUrl, $params ) : $instanceUrl;

		$options = [];
		if ( $method !== 'GET' ) {
			$options['postData'] = $params;
		}
		$request = $this->httpRequestFactory->create( $url, $options );
		$at = $instanceEntity->getConfig()['accessToken'];
		if ( $at ) {
			$request->setHeader( 'Authorization', 'Bearer ' . $at );
		}
		$status = $request->execute();
		if ( !$status->isOK() ) {
			return $status;
		}
		$content = $request->getContent();
		return Status::newGood( $content );
	}
}
