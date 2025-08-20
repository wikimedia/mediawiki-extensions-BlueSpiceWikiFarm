<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MediaWiki\Permissions\PermissionManager;
use Wikimedia\ParamValidator\ParamValidator;

class CreateTeamHandler extends RightManagementHandler {

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
		$bodyParams = $this->getValidatedBody();
		$team = $this->teamManager->createTeam( $params['teamName'], $bodyParams['description'], $this->getActor() );
		return $this->getResponseFactory()->createJson( $team );
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

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'description' => [
				'type' => 'string',
				'required' => true
			]
		];
	}
}
