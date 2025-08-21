<?php

namespace BlueSpice\WikiFarm\Process\Step;

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
		$vault = $this->getInstance()->getVault( $this->getInstanceManager()->getFarmConfig() );
		if ( file_exists( $vault ) ) {
			$this->getInstanceManager()->getLogger()->info(
				"Removing vault {path} ...",
				[ 'path' => $vault ]
			);
			wfRecursiveRemoveDir( $vault );
		}
		$this->dropInstanceDatabase();

		return array_merge( $data, [ 'success' => true ] );
	}

}
