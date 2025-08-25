<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use Exception;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteTeamHandler extends RightManagementHandler {

	/**
	 * @param PermissionManager $permissionManager
	 * @param TeamManager $teamManager
	 */
	public function __construct(
		PermissionManager $permissionManager,
		private readonly TeamManager $teamManager
	) {
		parent::__construct( $permissionManager );
	}

	public function execute() {
		$this->assertActorCan();
		$params = $this->getValidatedParams();
		try {
			$team = $this->teamManager->getTeam( $params['teamName'] );
		} catch ( Exception $e ) {
			throw new HttpException( $e->getMessage(), 404 );
		}

		$this->teamManager->deleteTeam( $team, $this->getActor() );
		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'teamName' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}
}
