<?php

namespace BlueSpice\WikiFarm\Process\Step;

use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\ProcessManager\IProcessStep;
use RuntimeException;

class CreateInstanceVault extends InstanceAwareStep implements IProcessStep {

	/**
	 * @param array $data
	 * @return array
	 * @throws RuntimeException
	 */
	public function execute( $data = [] ): array {
		sleep( 5 );
		$instance = $this->getInstance();
		$instanceVault = $instance->getVault( $this->getInstanceManager()->getFarmConfig() );

		if ( file_exists( $instanceVault ) ) {
			throw new RuntimeException(
				Message::newFromKey( 'wikifarm-error-createinstancevault-target-exists' )->plain()
			);
		}

		wfMkdirParents( "$instanceVault/cache" );
		wfMkdirParents( "$instanceVault/images" );
		wfMkdirParents( "$instanceVault/extensions/BlueSpiceFoundation/data" );

		$this->getInstanceManager()->getLogger()->info(
			'Created instance vault for instance path {instancePath}, at {instanceVault}',
			[
				'instancePath' => $instance->getPath(),
				'instanceVault' => $instanceVault
			]
		);

		return array_merge(
			$data,
			[ 'success' => true ]
		);
	}
}
