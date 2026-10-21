<?php

namespace BlueSpice\WikiFarm\Rest;

use BlueSpice\WikiFarm\Import\PackageValidator;
use BlueSpice\WikiFarm\InstanceManager;
use JsonException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestInterface;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageTransaction;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;
use Throwable;
use Wikimedia\ParamValidator\ParamValidator;

class VerifyImportPackageHandler extends SimpleHandler {

	public function __construct(
		private readonly InstanceManager $instanceManager,
		private readonly PermissionManager $permissionManager,
		private readonly StorageHandler $storage,
		private readonly PackageValidator $packageValidator
	) {
	}

	/**
	 * @return Response
	 * @throws HttpException
	 * @throws JsonException
	 */
	public function execute() {
		$this->assertRootCall();
		$this->assertManagePermission();

		$body = $this->getValidatedBody() ?? [];
		$upload = $this->getRequest()->getUploadedFiles()['file'] ?? null;
		$providedChecksum = isset( $body['checksum'] ) ? trim( (string)$body['checksum'] ) : '';
		if ( !$upload instanceof UploadedFileInterface ) {
			$this->assertPostSizeNotExceeded();
			throw new HttpException( 'Missing import package upload', 400 );
		}
		$this->assertUploadSucceeded( $upload );

		$originalFilename = $upload->getClientFilename() ?? '';
		if ( !preg_match( '/\\.bluespice$/', $originalFilename ) ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-name-invalid' )->text(),
				code: 400
			);
		}

		$sanitizedFilename = preg_replace( '/[^A-Za-z0-9._-]/', '-', pathinfo( $originalFilename, PATHINFO_FILENAME ) );
		$sanitizedFilename = trim( (string)$sanitizedFilename, '-._' );
		if ( $sanitizedFilename === '' ) {
			$sanitizedFilename = 'import-package';
		}
		$packageName = $sanitizedFilename . '-' . substr( md5( uniqid( '', true ) ), 0, 10 ) . '.bluespice';
		$tempUploadPath = $this->storage->getTempFilePath( $packageName, '_import_upload', true );
		try {
			$upload->moveTo( $tempUploadPath );
		} catch ( Throwable $e ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-upload-failed' )->text(), 500, [ 'previous' => $e ]
			);
		}
		if ( filesize( $tempUploadPath ) === 0 ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-upload-failed' )->text(), 400
			);
		}
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mimeType = $finfo ? finfo_file( $finfo, $tempUploadPath ) : false;
		if ( $mimeType !== 'application/zip' ) {
			throw new HttpException( Message::newFromKey( 'wikifarm-import-error-package-mime-invalid' )->text(), 400 );
		}

		$instanceStorageBackend = $this->storage->getBackend(
			$this->instanceManager->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		$storeStatus = ( new StorageTransaction( $instanceStorageBackend ) )
			->setContainer( 'export-import' )
			->store( $tempUploadPath, $packageName, '_import' )
			->commit();
		if ( !$storeStatus->isOK() ) {
			throw new HttpException( Message::newFromKey( 'wikifarm-import-error-package-upload-failed' )->text(), 500 );
		}

		try {
			$status = $this->packageValidator->validate( $packageName );
		} catch ( Throwable $e ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-invalid' )->text(), 400, [ 'previous' => $e ]
			);
		}
		if ( !$status->isOK() ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-invalid' )->text(), 400
			);
		}

		$validationData = $status->getValue();
		if ( !is_array( $validationData ) || !isset( $validationData['manifest'] ) || !is_array( $validationData['manifest'] ) ) {
			throw new RuntimeException( Message::newFromKey( 'wikifarm-import-error-package-verification' )->text() );
		}
		$manifest = $validationData['manifest'];
		$calculatedChecksum = (string)( $manifest['mainChecksum'] ?? '' );
		$checksumMatches = $providedChecksum === '' || hash_equals( $providedChecksum, $calculatedChecksum );
		if ( !$checksumMatches ) {
			throw new HttpException( Message::newFromKey( 'wikifarm-import-error-package-checksum-mismatch' )->text(), 400 );
		}

		$warnings = [];
		foreach ( $status->getMessages( 'warning' ) as $warning ) {
			$warnings[] = Message::newFromSpecifier( $warning )->text();
		}

		return $this->getResponseFactory()->createJson( [
			'valid' => true,
			'packageName' => $packageName,
			'importPackagePath' => $validationData['importPackagePath'] ?? null,
			'checksum' => $calculatedChecksum,
			'warnings' => $warnings,
		] );
	}

	/**
	 * @return true
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return true
	 */
	public function needsWriteAccess() {
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getBodyParamSettings(): array {
		return [
			'checksum' => [
				static::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
			],
		];
	}

	/**
	 * @return array|string[]
	 */
	public function getSupportedRequestTypes(): array {
		return [
			RequestInterface::MULTIPART_FORM_DATA_CONTENT_TYPE,
		];
	}

	/**
	 * When the request body is larger than `post_max_size`, PHP discards it completely,
	 * leaving both $_POST and $_FILES empty. Detect that and report it properly.
	 *
	 * @throws HttpException
	 */
	private function assertPostSizeNotExceeded(): void {
		$contentLength = (int)( $this->getRequest()->getHeaderLine( 'Content-Length' ) ?: 0 );
		$postMaxSize = $this->parseIniSize( (string)ini_get( 'post_max_size' ) );
		if ( $postMaxSize > 0 && $contentLength > $postMaxSize ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-upload-exceeded' )->text(),
				413
			);
		}
	}

	/**
	 * @param UploadedFileInterface $upload
	 * @throws HttpException
	 */
	private function assertUploadSucceeded( UploadedFileInterface $upload ): void {
		$error = $upload->getError();
		if ( $error === UPLOAD_ERR_OK ) {
			return;
		}
		if ( $error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE ) {
			throw new HttpException(
				Message::newFromKey( 'wikifarm-import-error-package-upload-exceeded' )->text(),
				413
			);
		}
		if ( $error === UPLOAD_ERR_NO_FILE ) {
			throw new HttpException( Message::newFromKey( 'wikifarm-import-error-package-upload-failed' )->text(), 400 );
		}
		throw new HttpException( Message::newFromKey( 'wikifarm-import-error-package-upload-failed' )->text(), 400 );
	}

	/**
	 * @param string $value
	 * @return int Size in bytes, 0 if unlimited/unparseable
	 */
	private function parseIniSize( string $value ): int {
		$value = trim( $value );
		if ( $value === '' ) {
			return 0;
		}
		$unit = strtolower( substr( $value, -1 ) );
		$number = (int)$value;
		switch ( $unit ) {
			case 'g':
				return $number * 1024 * 1024 * 1024;
			case 'm':
				return $number * 1024 * 1024;
			case 'k':
				return $number * 1024;
			default:
				return $number;
		}
	}

	/**
	 * @throws HttpException
	 */
	private function assertManagePermission(): void {
		if ( !$this->permissionManager->userHasRight( RequestContext::getMain()->getUser(), 'wikifarm-managewiki' ) ) {
			throw new HttpException( 'Permission denied', 403 );
		}
	}

	/**
	 * @throws HttpException
	 */
	private function assertRootCall(): void {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || !FARMER_IS_ROOT_WIKI_CALL ) {
			throw new HttpException( 'This call is only available from the root instance', 409 );
		}
	}
}
