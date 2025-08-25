<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\Config;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\DatabaseFactory;
use Wikimedia\Rdbms\IDatabase;

class ManagementDatabaseFactory {

	/**
	 * @param Config $config
	 */
	public function __construct(
		private readonly Config $config
	) {
	}

	/**
	 * @return Database
	 */
	public function createManagementConnection(): IDatabase {
		return $this->createDatabaseConnection(
			$this->config->get( 'managementDBname' ),
			$this->config->get( 'managementDBprefix' )
		);
	}

	/**
	 * @return Database
	 */
	public function createSharedUserDatabaseConnection(): IDatabase {
		[ $name, $prefix ] = $this->getSharedUserDbData();
		return $this->createDatabaseConnection( $name, $prefix );
	}

	/**
	 * @param string $name
	 * @param string $prefix
	 * @return Database
	 */
	private function createDatabaseConnection( string $name, string $prefix ): IDatabase {
		return ( new DatabaseFactory() )->create(
			$this->config->get( 'managementDBtype' ), [
				'host' => $this->config->get( 'managementDBserver' ),
				'user' => $this->config->get( 'managementDBuser' ),
				'password' => $this->config->get( 'managementDBpassword' ),
				'tablePrefix' => $prefix,
				'dbname' => $name,
			]
		);
	}

	/**
	 * @return array
	 */
	private function getSharedUserDbData(): array {
		if ( $this->config->get( 'sharedUserDBname' ) ) {
			return [ $this->config->get( 'sharedUserDBname' ), $this->config->get( 'sharedUserDBprefix' ) ];
		}
		return [ $this->config->get( 'managementDBname' ), $this->config->get( 'managementDBprefix' ) ];
	}
}
