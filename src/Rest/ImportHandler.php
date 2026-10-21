<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\Import\ImporterFactory;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Config\Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use Wikimedia\ParamValidator\ParamValidator;

class ImportHandler extends RightManagementHandler {

	/**
	 * @param PermissionManager $permissionManager
	 * @param InstanceManager $instanceManager
	 * @param Config $config
	 */
	public function __construct(
		PermissionManager $permissionManager,
		private readonly InstanceManager $instanceManager,
		private readonly Config $config
	) {
		parent::__construct( $permissionManager );
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'packageName' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @return Response|mixed
	 * @throws HttpException
	 */
	public function execute() {
		if ( !$this->config->get( 'WikiFarmEnableImportExport' ) ) {
			throw new HttpException( 'Import/export is not enabled', 403 );
		}

		$this->assertActorCan( 'wikifarm-managewiki' );
		$packageName = $this->getValidatedParams()['packageName'];

		$pid = $this->instanceManager->importInstance( $packageName, new ImporterFactory() );

		return $this->getResponseFactory()->createFromReturnValue( $pid );
	}
}
