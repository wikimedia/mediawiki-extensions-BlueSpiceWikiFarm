<?php

namespace BlueSpice\WikiFarm\Util;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use MediaWiki\Config\Config;
use RuntimeException;
use Wikimedia\Rdbms\IDatabase;

class DoctrineDatabaseConnection {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @param IDatabase $database
	 * @return Connection
	 * @throws Exception
	 */
	public function getDoctrineConnectionForMediawikiConnection( IDatabase $database ): Connection {
		return DriverManager::getConnection( [
			'driver' => $this->resolveDoctrineDriver( $database->getType() ),
			'host' => $database->getServer(),
			'user' => $this->farmConfig->get( 'dbAdminUser' ),
			'password' => $this->farmConfig->get( 'dbAdminPassword' ),
			'dbname' => $database->getDBname(),
		] );
	}

	/**
	 * @param string $dbType
	 * @return string
	 */
	private function resolveDoctrineDriver( string $dbType ): string {
		return match ( $dbType ) {
			'mysql' => 'mysqli',
			'postgres', 'postgresql' => 'pdo_pgsql',
			'sqlite' => 'pdo_sqlite',
			default => throw new RuntimeException( "Unsupported database type for schema export: {$dbType}" ),
		};
	}
}
