<?php

namespace BlueSpice\WikiFarm\Process;

use BlueSpice\WikiFarm\Process\Step\CloneAccess;
use BlueSpice\WikiFarm\Process\Step\CopyInstanceData;

class CloneInstance extends CreateInstance {

	/**
	 * @return array
	 */
	public function getSteps(): array {
		$steps = parent::getSteps();
		// Insert step after 'install-instance'
		$indexOf = array_search( 'install-instance', array_keys( $steps ) );
		$steps = array_merge(
			array_slice( $steps, 0, $indexOf + 1 ),
			[
				'copy-instance-data' => [
					'class' => CopyInstanceData::class,
					'args' => [ $this->data['instanceId'], $this->data['sourceInstanceId'] ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'DBLoadBalancer', 'MWStake.StorageUtilities' ]
				],
				'clone-access' => [
					'class' => CloneAccess::class,
					'args' => [ $this->data['instanceId'], $this->data['userName'], $this->data['sourceInstanceId'] ],
					'services' => [
						'BlueSpiceWikiFarm.InstanceManager', 'DBLoadBalancer', 'BlueSpiceWikiFarm.AccessControlStore',
						'BlueSpiceWikiFarm.GroupRoleManager', 'BlueSpiceWikiFarm.WikiAccessLookup'
					]
				]
			],
			array_slice( $steps, $indexOf + 1 )
		);

		// We do not assign current actor as admin on target instance when cloning
		unset( $steps['copy-user'] );

		return $steps;
	}
}
