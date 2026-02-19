<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\WikiMap\WikiMap;
use Wikimedia\ParamValidator\ParamValidator;

class UserAccessibleInstancesHandler extends SimpleHandler {

	/**
	 * @param Config $farmConfig
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 * @param FarmWikiMap $wikiMap
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly IAccessStore $accessStore,
		private readonly InstanceStore $instanceStore,
		private readonly FarmWikiMap $wikiMap
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
		if ( !$this->farmConfig->get( 'shareUsers' ) || !$this->farmConfig->get( 'shareUserSessions' ) ) {
			// Not valid request in this kind of setup
			return $this->getResponseFactory()->createJson( [] );
		}
		$role = $this->getValidatedParams()['role'];
		if ( !isset( IAccessStore::ROLES[$role] ) ) {
			throw new HttpException( "Invalid role: $role", 400 );
		}
		$user = RequestContext::getMain()->getUser();
		$allowed = $this->accessStore->getInstancePathsWhereUserHasRole( $user, 'reader' );
		$instances = [];
		foreach ( $allowed as $path ) {
			$instance = $this->instanceStore->getInstanceByPath( $path );
			if ( !$instance ) {
				continue;
			}
			if (
				$this->getValidatedParams()['skip_current' ] &&
				$instance->getWikiId() === WikiMap::getCurrentWikiId()
			) {
				continue;
			}
			$instances[] = $this->wikiMap->getWikiInfoFromInstance( $instance );
		}
		return $this->getResponseFactory()->createJson( $instances );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'role' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 'reader'
			],
			'skip_current' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => true
			]
		];
	}
}
