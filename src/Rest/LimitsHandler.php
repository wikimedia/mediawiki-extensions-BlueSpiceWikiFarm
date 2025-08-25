<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;

class LimitsHandler extends SimpleHandler {

	/**
	 * @var InstanceCountLimiter
	 */
	private $countLimiter;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @param PermissionManager $permissionManager
	 * @param InstanceCountLimiter $countLimiter
	 */
	public function __construct( PermissionManager $permissionManager, InstanceCountLimiter $countLimiter ) {
		$this->permissionManager = $permissionManager;
		$this->countLimiter = $countLimiter;
	}

	public function execute() {
		$this->assertRootCall();
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'wikifarm-managewiki' ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
		if ( $this->countLimiter->isLimited() ) {
			return $this->getResponseFactory()->createJson( [
				'limited' => true,
				'limit' => $this->countLimiter->getLimit(),
				'active' => $this->countLimiter->getCurrentActiveCount()
			] );
		}
		return $this->getResponseFactory()->createJson( [
			'limited' => false
		] );
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertRootCall() {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || !FARMER_IS_ROOT_WIKI_CALL ) {
			throw new HttpException( 'This call is only available from the root instance', 409 );
		}
	}
}
