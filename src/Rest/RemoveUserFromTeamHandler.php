<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;

class RemoveUserFromTeamHandler extends RightManagementHandler {

	/**
	 * @param PermissionManager $permissionManager
	 * @param TeamManager $teamManager
	 * @param UserFactory $userFactory
	 */
	public function __construct(
		PermissionManager $permissionManager,
		private readonly TeamManager $teamManager,
		private readonly UserFactory $userFactory
	) {
		parent::__construct( $permissionManager );
	}

	public function execute() {
		$this->assertActorCan();
		$params = $this->getValidatedParams();
		$bodyParams = $this->getValidatedBody();
		$team = $this->teamManager->getTeam( $params['teamName'] );
		$user = $this->userFactory->newFromName( $bodyParams['user'] );
		if ( !$user || !$user->isRegistered() ) {
			throw new HttpException( 'User not found', 404 );
		}
		$this->teamManager->removeUserFromTeam( $user, $team, $this->getActor() );
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

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'user' => [
				self::PARAM_SOURCE => 'body',
				'type' => 'string',
				'required' => true
			]
		];
	}
}
