<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\Export\ExporterFactory;
use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;

class ExportHandler extends InstanceHandler {

	/**
	 * @param InstanceManager $instanceManager
	 * @param PermissionManager $permissionManager
	 * @param Config $config
	 */
	public function __construct(
		InstanceManager $instanceManager, PermissionManager $permissionManager,
		private readonly Config $config
	) {
		parent::__construct( $instanceManager, $permissionManager );
	}

	/**
	 * @return string[]
	 */
	protected function getRequiredPermissions(): array {
		return [ 'wikifarm-managewiki' ];
	}

	/**
	 * @param string $instanceName
	 * @param array $bodyParams
	 * @return Response
	 * @throws HttpException
	 */
	protected function doExecute( string $instanceName, array $bodyParams ): Response {
		if ( !$this->config->get( 'WikiFarmEnableImportExport' ) ) {
			throw new HttpException( 'Import/export is not enabled', 403 );
		}
		$instance = $this->getInstanceEntity( $instanceName );
		$pid = $this->getInstanceManager()->exportInstance(
			$instance, RequestContext::getMain()->getUser(), new ExporterFactory()
		);

		return $this->getResponseFactory()->createFromReturnValue( $pid );
	}
}
