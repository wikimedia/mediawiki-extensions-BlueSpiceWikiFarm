<?php

namespace BlueSpice\WikiFarm\ProcessQueue;

use Wikimedia\Rdbms\DatabaseFactory;
use Wikimedia\Rdbms\IDatabase;

trait GlobalProcessDatabaseTrait {

	/**
	 * @param int $type
	 * @return IDatabase
	 */
	protected function getDB( int $type = DB_REPLICA ): IDatabase {
		return ( new DatabaseFactory() )->create(
			$this->farmConfig->get( 'managementDBtype' ), [
				'host' => $this->farmConfig->get( 'managementDBserver' ),
				'user' => $this->farmConfig->get( 'managementDBuser' ),
				'password' => $this->farmConfig->get( 'managementDBpassword' ),
				'tablePrefix' => $this->farmConfig->get( 'managementDBprefix' ),
				'dbname' => $this->farmConfig->get( 'managementDBname' ),
			]
		);
	}
}
