<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Util\InstanceDisplayRecordHelper;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class InstanceDisplayData extends SimpleHandler {

	/**
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 * @param InstanceDisplayRecordHelper $instanceDisplayRecordHelper
	 */
	public function __construct(
		private readonly IAccessStore $accessStore,
		private readonly InstanceStore $instanceStore,
		private readonly InstanceDisplayRecordHelper $instanceDisplayRecordHelper
	) {
	}

	/**
	 * @return \MediaWiki\Rest\Response|mixed
	 * @throws HttpException
	 */
	public function execute() {
		$identifier = $this->getValidatedParams()['identifier'];

		$instance = $this->instanceStore->getInstanceByAny( $identifier );
		if ( !$instance ) {
			throw new HttpException( 'Instance not found' );
		}
		$canRead = $this->accessStore->userHasRoleOnInstance(
			RequestContext::getMain()->getUser(), 'reader', $instance
		);
		if ( !$canRead ) {
			throw new HttpException( 'Permission denied', 403 );
		}
		return $this->getResponseFactory()->createJson(
			$this->instanceDisplayRecordHelper->getDisplayRecord( $instance, RequestContext::getMain()->getUser() )
		);
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'identifier' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			]
		];
	}
}
