<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\Storage\InstanceTransaction;

class PurgeInstance extends ArchiveInstance {

	/**
	 * @param array $data
	 * @return array
	 * @throws \Exception
	 */
	public function execute( $data = [] ): array {
		$this->getInstanceManager()->getLogger()->info(
			"Purging instance {path} ...",
			[ 'path' => $this->getInstance()->getPath() ]
		);

		$this->setSharedSetupFlag();
		$backend = $this->storageHandler->getBackend(
			$this->getInstanceManager()->getFarmConfig()->get( 'instanceStorageBackend' )
		);
		( new InstanceTransaction( $backend ) )
			->deleteInstanceDirectory( $this->getInstance()->getPath() )
			->commit();

		$this->dropInstanceDatabase();

		return array_merge( $data, [ 'success' => true ] );
	}

}
