<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use Wikimedia\ParamValidator\ParamValidator;

class InstanceInfoFromWikiIDHandler extends SimpleHandler {

	/**
	 * @param IAccessStore $accessStore
	 * @param \Config $farmConfig
	 * @param FarmWikiMap $farmWikiMap
	 */
	public function __construct(
		private readonly IAccessStore $accessStore,
		private readonly \Config $farmConfig,
		private readonly FarmWikiMap $farmWikiMap
	) {
	}

	public function execute() {
		if ( !$this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			throw new HttpException( 'This endpoint only callable in global access control setup' );
		}

		$instance = $this->farmWikiMap->getInstanceByWikiId( $this->getValidatedParams()['wiki_id'] );
		if ( !$instance ) {
			throw new HttpException( 'Instance not found', 404 );
		}

		if ( !$this->accessStore->userHasRoleOnInstance( RequestContext::getMain()->getUser(), 'reader', $instance ) ) {
			throw new HttpException( 'Instance not found', 404 );
		}

		return $this->getResponseFactory()->createJson(
			$this->farmWikiMap->getWikiInfoFromInstance( $instance )
		);
	}

	/**
	 * @return bool
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'wiki_id' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}
}
