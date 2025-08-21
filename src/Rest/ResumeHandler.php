<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;

class ResumeHandler extends SuspendHandler {

	/**
	 * @var InstanceCountLimiter
	 */
	private $countLimiter;

	/**
	 * @param InstanceManager $instanceManager
	 * @param PermissionManager $permissionManager
	 * @param InstanceCountLimiter $countLimiter
	 */
	public function __construct(
		InstanceManager $instanceManager, PermissionManager $permissionManager, InstanceCountLimiter $countLimiter
	) {
		parent::__construct( $instanceManager, $permissionManager );
		$this->countLimiter = $countLimiter;
	}

	/**
	 * @return string
	 */
	protected function getStatusToSet(): string {
		return InstanceEntity::STATUS_READY;
	}

	protected function doExecute( string $instanceName, array $bodyParams ): Response {
		if ( !$this->countLimiter->canCreate() ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-error-instance-limit-reached' )->text(), 403
			);
		}
		return parent::doExecute( $instanceName, $bodyParams );
	}
}
