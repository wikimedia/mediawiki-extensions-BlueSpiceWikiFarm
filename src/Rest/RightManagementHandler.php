<?php

namespace BlueSpice\WikiFarm\Rest;

use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;

abstract class RightManagementHandler extends SimpleHandler {

	/**
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		protected readonly PermissionManager $permissionManager
	) {
	}

	/**
	 * @return Authority
	 */
	protected function getActor(): Authority {
		return RequestContext::getMain()->getUser();
	}

	/**
	 * @return true
	 */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @param string $right
	 * @return void
	 * @throws HttpException
	 */
	public function assertActorCan( string $right = 'wikiadmin' ) {
		if ( !$this->permissionManager->userHasRight( $this->getActor()->getUser(), $right ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
	}
}
