<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\WikiAccessLookup;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use InvalidArgumentException;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use Wikimedia\ParamValidator\ParamValidator;

class WikiAccessLevelHandler extends RightManagementHandler {

	/**
	 * @param PermissionManager $permissionManager
	 * @param WikiAccessLookup $wikiAccessLookup
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		PermissionManager $permissionManager,
		private readonly WikiAccessLookup $wikiAccessLookup,
		private readonly InstanceStore $instanceStore
	) {
		parent::__construct( $permissionManager );
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$this->assertActorCan( 'userrights' );
		$params = $this->getValidatedBody();
		$level = $params['level'];
		if ( !in_array( $level, GroupAccessStore::ACCESS_LEVELS ) ) {
			throw new HttpException( "Invalid access level: $level", 400 );
		}

		try {
			$this->wikiAccessLookup->setAccessLevelForInstance( $this->getInstance(), $level );
		} catch ( InvalidArgumentException $ex ) {
			throw new HttpException( $ex->getMessage(), 400 );
		}

		return $this->getResponseFactory()->createJson( [
			'success' => true,
			'accessLevel' => $level
		] );
	}

	/**
	 * @return InstanceEntity
	 * @throws HttpException
	 */
	private function getInstance(): InstanceEntity {
		$instance = $this->instanceStore->getInstanceByPath( FARMER_CALLED_INSTANCE );
		if ( !$instance ) {
			throw new HttpException( 'Instance not found', 404 );
		}
		return $instance;
	}

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'level' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => GroupAccessStore::ACCESS_LEVELS,
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}
}
