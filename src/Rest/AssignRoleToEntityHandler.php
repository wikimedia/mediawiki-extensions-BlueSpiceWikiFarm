<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserGroupManager;
use Wikimedia\ParamValidator\ParamValidator;

class AssignRoleToEntityHandler extends RightManagementHandler {

	/**
	 * @param PermissionManager $permissionManager
	 * @param TeamManager $teamManager
	 * @param UserFactory $userFactory
	 * @param UserGroupManager $userGroupManager
	 * @param InstanceGroupCreator $instanceGroupCreator
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		PermissionManager $permissionManager,
		private readonly TeamManager $teamManager,
		private readonly UserFactory $userFactory,
		private readonly UserGroupManager $userGroupManager,
		private readonly InstanceGroupCreator $instanceGroupCreator,
		private readonly InstanceStore $instanceStore
	) {
		parent::__construct( $permissionManager );
	}

	public function execute() {
		$this->assertActorCan( 'userrights' );
		$params = $this->getValidatedBody();
		if ( $params['entityType'] === 'user' ) {
			return $this->assignRoleToUser( $params );
		} elseif ( $params['entityType'] === 'team' ) {
			return $this->assignRoleToTeam( $params );
		}
		throw new HttpException( 'Invalid entity type', 400 );
	}

	/**
	 * @param array $params
	 * @return Response
	 * @throws HttpException
	 */
	private function assignRoleToUser( array $params ) {
		$user = $this->userFactory->newFromName( $params['entityKey'] );
		if ( !$user || !$user->isRegistered() ) {
			throw new HttpException( 'User not found', 404 );
		}
		$instance = $this->getInstance();
		if ( $params['globalAssignment'] ) {
			$allGroups = $this->instanceGroupCreator->getGlobalGroups();
		} else {
			$allGroups = $this->instanceGroupCreator->getGroupsAndRolesForInstancePath( $instance->getPath() );
		}
		foreach ( array_keys( $allGroups ) as $group ) {
			$this->userGroupManager->removeUserFromGroup( $user, $group );
		}
		if ( !$params['roleName'] ) {
			// Un-assign all roles
			return $this->getResponseFactory()->createJson( [ 'success' => true ] );
		}
		if ( $params['globalAssignment'] ) {
			$groupName = $this->instanceGroupCreator->getGroupNameForUserRole( '_global', $params['roleName'] );
		} else {
			$groupName = $this->instanceGroupCreator->getGroupNameForUserRole( $instance->getPath(), $params['roleName'] );
		}
		if ( !$groupName ) {
			throw new HttpException( 'Role not found', 404 );
		}
		return $this->getResponseFactory()->createJson( [
			'success' => $this->userGroupManager->addUserToGroup( $user, $groupName )
		] );
	}

	/**
	 * @param array $params
	 * @return Response
	 * @throws HttpException
	 */
	private function assignRoleToTeam( array $params ) {
		$team = $this->teamManager->getTeam( $params['entityKey'] );
		$instance = $this->getInstance();
		$isGlobal = $params['globalAssignment'];
		if ( $isGlobal ) {
			$this->teamManager->removeAllRoles( $team, null, $this->getActor(), (bool)$params['roleName'] );
		} else {
			$this->teamManager->removeAllRoles( $team, $instance, $this->getActor(), (bool)$params['roleName'] );
		}

		if ( !$params['roleName'] ) {
			return $this->getResponseFactory()->createJson( [ 'success' => true ] );
		}

		$this->teamManager->assignRoleToTeam(
			$params['roleName'], $team, $isGlobal ? null : $instance, $this->getActor()
		);

		return $this->getResponseFactory()->createJson( [ 'success' => true ] );
	}

	/**
	 * @return InstanceEntity
	 * @throws HttpException
	 */
	private function getInstance(): InstanceEntity {
		$path = FARMER_CALLED_INSTANCE;
		$instance = $this->instanceStore->getInstanceByPath( $path );
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
			'entityType' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'entityKey' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'roleName' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'globalAssignment' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_DEFAULT => false
			],
		];
	}
}
