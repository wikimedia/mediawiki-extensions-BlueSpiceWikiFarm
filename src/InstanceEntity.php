<?php

namespace BlueSpice\WikiFarm;

use DateTime;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use RuntimeException;
use Wikimedia\Rdbms\DatabaseDomain;

class InstanceEntity {

	public const STATUS_INIT = 'initializing';
	public const STATUS_INSTALLED = 'installed';
	public const STATUS_READY = 'ready';
	public const STATUS_SUSPENDED = 'suspended';
	public const STATUS_MAINTENANCE = 'maintenance';
	public const STATUS_ARCHIVED = 'archived';

	/** @var string */
	private $id;
	/** @var string */
	private $path;
	/** @var string */
	private $displayName;
	/** @var DateTime */
	private $created;
	/** @var DateTime */
	private $updated;
	/** @var string */
	private $status;
	/** @var string */
	private $dbName;
	/** @var string */
	private $dbPrefix;
	/** @var array */
	private $metadata;
	/** @var array */
	private $config;

	/**
	 * @param string $id
	 * @param string $path
	 * @param string $displayName
	 * @param DateTime $created
	 * @param DateTime $updated
	 * @param string $status
	 * @param string $dbName
	 * @param string $dbPrefix
	 * @param array $metadata
	 * @param array $config
	 */
	public function __construct(
		string $id, string $path, string $displayName, DateTime $created, DateTime $updated, string $status,
		string $dbName, string $dbPrefix, array $metadata, array $config
	) {
		$this->id = $id;
		$this->created = $created;
		$this->dbName = $dbName;
		$this->dbPrefix = $dbPrefix;
		$this->metadata = $metadata;
		$this->config = $config;
		$this->setPath( $path );
		$this->setDisplayName( $displayName );
		$this->updated = $updated;
		$this->setStatus( $status );
	}

	/**
	 * @return string
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * @param Config $farmConfig
	 * @return string
	 */
	public function getScriptPath( Config $farmConfig ): string {
		$base = $farmConfig->get( 'basePath' );
		$base = rtrim( $base, '/' );
		return $base . '/' . $this->getPath();
	}

	/**
	 * @param Config $farmConfig
	 * @return string
	 */
	public function getUrl( Config $farmConfig ): string {
		return $this->trimSlashes( $farmConfig->get( 'globalServer' ) ) .
			'/' .
			ltrim( $this->getScriptPath( $farmConfig ), '/' );
	}

	/**
	 * @return string
	 */
	public function getDisplayName(): string {
		return $this->displayName;
	}

	/**
	 * @return DateTime
	 */
	public function getCreated(): DateTime {
		return $this->created;
	}

	/**
	 * @return DateTime
	 */
	public function getUpdated(): DateTime {
		return $this->updated;
	}

	/**
	 * @return string
	 */
	public function getStatus(): string {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function getDbName(): string {
		return $this->dbName;
	}

	/**
	 * @return string
	 */
	public function getDbPrefix(): string {
		return $this->dbPrefix;
	}

	/**
	 * @return array
	 */
	public function getMetadata(): array {
		return $this->metadata;
	}

	/**
	 * @return array
	 */
	public function getConfig(): array {
		return $this->config;
	}

	/**
	 * @param string $path
	 * @return void
	 */
	public function setPath( string $path ) {
		$this->path = $path;
		$this->updateTouched();
	}

	/**
	 * @param string $displayName
	 * @return void
	 */
	public function setDisplayName( string $displayName ) {
		if ( strlen( $displayName ) > 255 ) {
			throw new InvalidArgumentException(
				Message::newFromKey( 'wikifarm-error-display-name-too-long' )->plain()
			);
		}
		$this->displayName = $displayName;
		$this->updateTouched();
	}

	/**
	 * @param string $status
	 * @return void
	 */
	public function setStatus( string $status ) {
		// Check is status is one of the allowed values
		if ( !in_array( $status, [
			self::STATUS_INIT,
			self::STATUS_INSTALLED,
			self::STATUS_READY,
			self::STATUS_SUSPENDED,
			self::STATUS_MAINTENANCE,
			self::STATUS_ARCHIVED
		] ) ) {
			throw new RuntimeException( 'Unsupported status' );
		}
		$this->status = $status;
		$this->updateTouched();
	}

	/**
	 * @param array $metadata
	 * @return void
	 */
	public function setMetadata( array $metadata ) {
		$this->metadata = $metadata;
		$this->updateTouched();
	}

	/**
	 * @param string $key
	 * @param mixed $value
	 * @return void
	 */
	public function setConfigItem( string $key, $value ) {
		$this->config[$key] = $value;
	}

	/**
	 * @param string $key
	 * @return void
	 */
	public function removeConfigItem( string $key ) {
		if ( isset( $this->config[$key] ) ) {
			unset( $this->config[$key] );
		}
	}

	/**
	 * @return array
	 */
	public function dbSerialize() {
		return [
			'sfi_id' => $this->id,
			'sfi_path' => $this->path,
			'sfi_display_name' => $this->displayName,
			'sfi_created' => $this->created->format( 'YmdHis' ),
			'sfi_touched' => $this->updated->format( 'YmdHis' ),
			'sfi_db_prefix' => $this->dbPrefix,
			'sfi_database' => $this->dbName,
			'sfi_meta' => json_encode( $this->metadata ),
			'sfi_config' => json_encode( $this->config ),
			'sfi_status' => $this->status
		];
	}

	/**
	 * @return bool
	 */
	public function isActive(): bool {
		return $this->getStatus() === static::STATUS_READY || $this->getStatus() === static::STATUS_MAINTENANCE;
	}

	/**
	 * @param Config $farmConfig
	 * @param bool $forWebAccess
	 * @return string
	 */
	public function getVault( Config $farmConfig, bool $forWebAccess = false ): string {
		if ( $forWebAccess ) {
			return $this->trimSlashes( $farmConfig->get( 'instancePath' ) ) . '/' . $this->getPath();
		}
		if ( !$farmConfig->has( 'instanceDirectory' ) ) {
			throw new RuntimeException(
				Message::newFromKey( 'wikifarm-error-instance-directory-not-set' )->plain()
			);
		}
		return $farmConfig->get( 'instanceDirectory' ) . '/' . $this->getPath();
	}

	/**
	 * Is the instance completely installed
	 * @return bool
	 */
	public function isComplete(): bool {
		return $this->getStatus() !== static::STATUS_INIT && $this->getStatus() !== static::STATUS_INSTALLED;
	}

	/**
	 * @return DatabaseDomain
	 */
	public function getDatabaseDomain(): DatabaseDomain {
		return new DatabaseDomain( $this->getDbName(), null, $this->getDbPrefix() );
	}

	/**
	 * @return void
	 */
	private function updateTouched() {
		$this->updated = new DateTime();
	}

	/**
	 * @param string $input
	 * @return string
	 */
	private function trimSlashes( string $input ): string {
		return trim( $input, '/' );
	}
}
