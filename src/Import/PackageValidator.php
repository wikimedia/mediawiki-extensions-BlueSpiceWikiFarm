<?php

namespace BlueSpice\WikiFarm\Import;

use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use JsonException;
use MediaWiki\Config\Config;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Message\Message;
use MediaWiki\Status\Status;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHelper;
use MWStake\MediaWiki\Component\SchemaVersion\SchemaVersionManager;
use Psr\Log\LoggerInterface;
use RuntimeException;
use ZipArchive;

class PackageValidator {
	private const MAX_TRANSACTION_STEPS = 900;

	/**
	 * @param Config $farmConfig
	 * @param StorageHandler $storage
	 * @param LoggerInterface $logger
	 * @param ManagementDatabaseFactory $managementDatabaseFactory
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly StorageHandler $storage,
		private readonly LoggerInterface $logger,
		private readonly ManagementDatabaseFactory $managementDatabaseFactory,
		private readonly HookContainer $hookContainer
	) {
	}

	/**
	 * @param string $packageName
	 * @return Status
	 * @throws JsonException
	 */
	public function validate( string $packageName ): Status {
		$this->assertValidPackageName( $packageName );

		$instanceStorageBackend = $this->storage->getBackend(
			$this->farmConfig->get( 'instanceStorageBackend' )
		);
		$storageHelper = new StorageHelper( $instanceStorageBackend );
		$sourcePath = $storageHelper->compileZonePath( '_import', $packageName, 'export-import' );
		$localPackage = $instanceStorageBackend->getLocalCopy( [ 'src' => $sourcePath ] );
		if ( !$localPackage ) {
			$this->logger->error( 'Package not found: {packageName}, sourcePath: {sourcePath}', [
				'packageName' => $packageName,
				'sourcePath' => $sourcePath,
			] );
			return Status::newFatal( Message::newFromKey( 'wikifarm-import-error-package-not-found' )->text() );
		}

		$extractPathKey = $this->getExtractPathKey( $packageName );
		$manifestPath = $this->storage->getTempFilePath( 'manifest.json', $extractPathKey, true );
		$extractDirectory = dirname( $manifestPath );

		$zip = new ZipArchive();
		$openResult = $zip->open( $localPackage->getPath() );
		if ( $openResult !== true ) {
			$this->logger->error(
				'Failed to open package: {packageName}, path: {packagePath},
				openResult: {openResult}, zipStatus: {zipStatus}',
				[
					'packageName' => $packageName,
					'packagePath' => $localPackage->getPath(),
					'openResult' => $openResult,
					'zipStatus' => $zip->getStatusString(),
				]
			);
			return Status::newFatal( Message::newFromKey( 'wikifarm-import-error-package-invalid' )->text() );
		}

		if ( !$zip->extractTo( $extractDirectory ) ) {
			$zipStatus = $zip->getStatusString();
			$zip->close();
			$this->logger->error(
				'Failed to extract package contents: {packageName}, path: {packagePath},
				extractDirectory: {extractDirectory}, extractDirectoryExists: {extractDirectoryExists},
				extractDirectoryWritable: {extractDirectoryWritable}, zipStatus: {zipStatus}',
				[
					'packageName' => $packageName,
					'packagePath' => $localPackage->getPath(),
					'extractDirectory' => $extractDirectory,
					'extractDirectoryExists' => is_dir( $extractDirectory ),
					'extractDirectoryWritable' => is_writable( $extractDirectory ),
					'zipStatus' => $zipStatus,
				]
			);
			return Status::newFatal( Message::newFromKey( 'wikifarm-import-error-package-invalid' )->text() );
		}
		$zip->close();

		if ( !file_exists( $manifestPath ) ) {
			return Status::newFatal( Message::newFromKey( 'wikifarm-import-error-package-invalid' )->text() );
		}

		$manifestRaw = file_get_contents( $manifestPath );
		if ( $manifestRaw === false ) {
			$this->logger->error(
			 'Failed to read manifest file from package: {packageName}', [ 'packageName' => $packageName ]
			);
			return Status::newFatal( Message::newFromKey( 'wikifarm-import-error-package-invalid' )->text() );
		}
		$manifestData = json_decode( $manifestRaw, true, 512, JSON_THROW_ON_ERROR );

		$this->verifyMainChecksum( $manifestData );
		$this->verifyManifestFiles( $manifestData, $extractDirectory );

		$status = Status::newGood( [
			'manifest' => $manifestData,
			'importPackagePath' => $extractPathKey,
		] );
		$warnings = $this->verifyEnvironment( $manifestData['env'] ?? [] );
		if ( !empty( $warnings ) ) {
			foreach ( $warnings as $warning ) {
				$status->warning( $warning );
			}
		}

		$this->checkIncomingSchema( $manifestData['schemaVersion'] ?? null, $status );

		return $status;
	}

	/**
	 * @param string $packageName
	 * @return void
	 */
	public function cleanupTempFiles( string $packageName ): void {
		$this->assertValidPackageName( $packageName );
		$extractPathKey = $this->getExtractPathKey( $packageName );
		$tempBackend = $this->storage->getTempBackend();
		$storageHelper = new StorageHelper( $tempBackend );
		$directory = $storageHelper->compileZonePath( $extractPathKey );
		$files = $tempBackend->getFileList( [
			'dir' => $directory,
			'topOnly' => false,
		] );

		$transaction = $this->storage->newTempTransaction();
		$steps = 0;
		foreach ( $files as $file ) {
			$transaction->delete( $file, $extractPathKey );
			$steps++;
			if ( $steps >= self::MAX_TRANSACTION_STEPS ) {
				$status = $transaction->commit();
				if ( !$status->isOK() ) {
					$this->logger->warning( 'wikifarm-import-warning-package-temp-cleanup-failed', [
						'packageName' => $packageName,
						'extractPathKey' => $extractPathKey,
						'errors' => $status->getErrors(),
					] );
					return;
				}
				$transaction = $this->storage->newTempTransaction();
				$steps = 0;
			}
		}

		if ( $steps > 0 ) {
			$status = $transaction->commit();
			if ( !$status->isOK() ) {
				$this->logger->warning( 'wikifarm-import-warning-package-temp-cleanup-failed', [
					'packageName' => $packageName,
					'extractPathKey' => $extractPathKey,
					'errors' => $status->getErrors(),
				] );
			}
		}
	}

	/**
	 * @param string $packageName
	 * @return void
	 */
	private function assertValidPackageName( string $packageName ): void {
		if ( !preg_match( '/^[A-Za-z0-9._-]+\\.bluespice$/', $packageName ) ) {
			throw $this->newPackageValidationException( 'wikifarm-import-error-package-name-invalid' );
		}
	}

	/**
	 * @param string $packageName
	 * @return string
	 */
	private function getExtractPathKey( string $packageName ): string {
		return '_import/' . pathinfo( $packageName, PATHINFO_FILENAME );
	}

	/**
	 * @param array $manifestData
	 * @return void
	 * @throws JsonException
	 */
	private function verifyMainChecksum( array $manifestData ): void {
		if ( !isset( $manifestData['mainChecksum'] ) || !is_string( $manifestData['mainChecksum'] ) ) {
			throw $this->newPackageValidationException( 'wikifarm-import-error-package-manifest-main-checksum-missing' );
		}
		$providedChecksum = $manifestData['mainChecksum'];
		unset( $manifestData['mainChecksum'] );

		$calculatedChecksum = md5( json_encode( $manifestData, JSON_THROW_ON_ERROR ) );
		if ( $providedChecksum !== $calculatedChecksum ) {
			throw $this->newPackageValidationException( 'wikifarm-import-error-package-manifest-main-checksum-mismatch' );
		}
	}

	/**
	 * @param array $manifestData
	 * @param string $extractDirectory
	 * @return void
	 */
	private function verifyManifestFiles( array $manifestData, string $extractDirectory ): void {
		if ( !isset( $manifestData['files'] ) || !is_array( $manifestData['files'] ) ) {
			throw $this->newPackageValidationException( 'wikifarm-import-error-package-manifest-files-missing' );
		}

		foreach ( $manifestData['files'] as $fileInfo ) {
			$file = $fileInfo['file'] ?? null;
			$path = $fileInfo['path'] ?? null;
			$checksum = $fileInfo['checksum'] ?? null;
			if ( !is_string( $file ) || !is_string( $path ) || !is_string( $checksum ) ) {
				throw $this->newPackageValidationException( 'wikifarm-import-error-package-manifest-file-entry-invalid' );
			}

			$packageRelativePath = $this->buildPackageRelativePath( $file, $path );
			$localPath = $extractDirectory . '/' . $packageRelativePath;
			if ( !file_exists( $localPath ) ) {
				throw $this->newPackageValidationException(
					'wikifarm-import-error-package-file-missing',
					[ $packageRelativePath ]
				);
			}

			$content = file_get_contents( $localPath );
			if ( $content === false ) {
				throw $this->newPackageValidationException(
					'wikifarm-import-error-package-file-read-failed',
					[ $packageRelativePath ]
				);
			}
			$calculatedChecksum = md5( $content );
			if ( $calculatedChecksum !== $checksum ) {
				throw $this->newPackageValidationException(
					'wikifarm-import-error-package-file-checksum-mismatch',
					[ $packageRelativePath ]
				);
			}
		}
	}

	/**
	 * @param string $key
	 * @param array $params
	 * @return RuntimeException
	 */
	private function newPackageValidationException( string $key, array $params = [] ): RuntimeException {
		return new RuntimeException( Message::newFromKey( $key )->params( $params )->text() );
	}

	/**
	 * @param string $filename
	 * @param string $path
	 * @return string
	 */
	private function buildPackageRelativePath( string $filename, string $path ): string {
		$relativePath = trim( $path, '/' );

		if ( $relativePath === '' ) {
			return $filename;
		}

		return $relativePath . '/' . $filename;
	}

	/**
	 * @param array $env
	 * @return array
	 */
	private function verifyEnvironment( array $env ): array {
		$warnings = [];
		foreach ( $env as $globalName => $globalValue ) {
			if ( is_string( $globalValue ) ) {
				if ( !isset( $GLOBALS[$globalName] ) ) {
					$warnings[] = Message::newFromKey(
						'wikifarm-import-warning-global-mismatch-not-set' )->params( [ $globalName, $globalValue ]
					);
					continue;
				}
				if ( $GLOBALS[$globalName] !== $globalValue ) {
					$warnings[] = Message::newFromKey(
						'wikifarm-import-warning-global-mismatch-value' )
						->params( [ $globalName, $globalValue, $GLOBALS[$globalName] ]
					);
				}
			}
		}
		return $warnings;
	}

	/**
	 * @param array|null $incomingVersion
	 * @param Status $status
	 * @return void
	 */
	private function checkIncomingSchema( ?array $incomingVersion, Status $status ): void {
		if ( !$incomingVersion ) {
			$status->warning(
				Message::newFromKey( 'wikifarm-import-warning-package-schema-version-missing' )
			);
			return;
		}
		$schemaVersionManager = new SchemaVersionManager( $this->hookContainer );
		$current = $schemaVersionManager->getCurrentVersion(
			$this->managementDatabaseFactory->getManagementConnection()
		);
		if ( !$current ) {
			$status->warning(
				Message::newFromKey( 'wikifarm-import-warning-package-schema-version-local-missing' )
			);
			return;
		}
		if ( $incomingVersion['hash'] === $current['hash'] ) {
			// All good
			return;
		}
		if ( $incomingVersion['timestamp'] > $current['timestamp'] ) {
			$status->warning(
				Message::newFromKey( 'wikifarm-import-warning-package-schema-version-mismatch' )
			);
		}
	}

}
