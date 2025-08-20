<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use Psr\Log\LoggerInterface;
use Wikimedia\ParamValidator\ParamValidator;

abstract class InstanceHandler extends SimpleHandler {

	/** @var InstanceManager */
	private $instanceManager;
	/** @var PermissionManager */
	private $permissionManager;
	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param InstanceManager $instanceManager
	 * @param PermissionManager $permissionManager
	 */
	public function __construct( InstanceManager $instanceManager, PermissionManager $permissionManager ) {
		$this->instanceManager = $instanceManager;
		$this->permissionManager = $permissionManager;
		$this->logger = LoggerFactory::getInstance( 'BlueSpiceWikiFarm' );
	}

	public function execute() {
		$this->assertRootCall();
		$this->assertPermissions( $this->getRequiredPermissions() );
		$instanceName = $this->getValidatedParams()['instanceName'];
		$bodyParams = $this->getValidatedBody() ?? [];

		return $this->doExecute( $instanceName, $bodyParams );
	}

	/**
	 * @param string $path
	 * @return InstanceEntity
	 * @throws HttpException
	 */
	protected function getInstanceEntity( string $path ): InstanceEntity {
		$instance = $this->getInstanceManager()->getStore()->getInstanceByPath( $path );
		if ( !$instance || $instance instanceof RootInstanceEntity ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-error-unknown-instance' )->text(), 404
			);
		}
		return $instance;
	}

	/**
	 * @param string $instanceName
	 * @param array $bodyParams
	 * @return Response
	 * @throws HttpException
	 */
	abstract protected function doExecute( string $instanceName, array $bodyParams ): Response;

	/**
	 * @return bool
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return bool
	 */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return InstanceManager
	 */
	protected function getInstanceManager(): InstanceManager {
		return $this->instanceManager;
	}

	/**
	 * @return LoggerInterface
	 */
	protected function getLogger(): LoggerInterface {
		return $this->logger;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'instanceName' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @param array $permissions
	 * @return void
	 * @throws HttpException
	 */
	private function assertPermissions( array $permissions ) {
		$userCan = $this->permissionManager->userHasAllRights(
			RequestContext::getMain()->getUser(),
			...$permissions
		);
		if ( !$userCan ) {
			throw new HttpException( 'Permission denied', 403 );
		}
	}

	/**
	 * @return string[]
	 */
	abstract protected function getRequiredPermissions(): array;

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
