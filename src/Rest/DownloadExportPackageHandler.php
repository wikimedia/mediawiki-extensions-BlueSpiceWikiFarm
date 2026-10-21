<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\InstanceManager;
use MediaWiki\Context\RequestContext;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\Rest\Stream;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHelper;
use Wikimedia\ParamValidator\ParamValidator;

class DownloadExportPackageHandler extends SimpleHandler {

	/**
	 * @param InstanceManager $instanceManager
	 * @param PermissionManager $permissionManager
	 * @param StorageHandler $storage
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		private readonly PermissionManager $permissionManager,
		private readonly StorageHandler $storage
	) {
	}

	/**
	 * @return Response
	 * @throws HttpException
	 */
	public function execute() {
		$this->assertRootCall();
		$this->assertWikiAdminPermission();

		$packageFile = $this->getValidatedParams()['packageFile'];
		if ( !preg_match( '/^[A-Za-z0-9._-]+\\.bluespice$/', $packageFile ) ) {
			throw new HttpException( 'Invalid package file name', 400 );
		}

		$backend = $this->storage->getBackend(
			$this->instanceManager->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		$storageHelper = new StorageHelper( $backend );
		$sourcePath = $storageHelper->compileZonePath( '', $packageFile, 'export-import' );

		$localFile = $backend->getLocalCopy( [ 'src' => $sourcePath ] );
		if ( !$localFile ) {
			throw new HttpException( 'Package not found', 404 );
		}

		$stream = fopen( $localFile->getPath(), 'rb' );
		if ( !$stream ) {
			throw new HttpException( 'Failed to open package file', 500 );
		}

		$response = $this->getResponseFactory()->create();
		$response->setHeader( 'Content-Type', 'application/octet-stream' );
		$response->setHeader( 'Content-Disposition', "attachment; filename=\"$packageFile\"" );
		$response->setBody( new Stream( $stream ) );

		return $response;
	}

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
		return false;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'packageFile' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertWikiAdminPermission(): void {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'wikiadmin' ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertRootCall(): void {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || !FARMER_IS_ROOT_WIKI_CALL ) {
			throw new HttpException( 'This call is only available from the root instance', 409 );
		}
	}
}
