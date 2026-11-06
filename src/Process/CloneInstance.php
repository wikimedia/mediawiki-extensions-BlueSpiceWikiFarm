<?php

namespace BlueSpice\WikiFarm\Process;

use BlueSpice\WikiFarm\Process\Step\CopyInstanceData;
use BlueSpice\WikiFarm\Process\Step\CopyUserOnClone;

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
				]
			],
			array_slice( $steps, $indexOf + 1 )
		);

		$steps['copy-user'] = [
			'class' => CopyUserOnClone::class,
			'args' => [ $this->data['instanceId'], $this->data['userName'], $this->data['sourceInstanceId'] ],
			'services' => [
				'BlueSpiceWikiFarm.InstanceManager', 'DBLoadBalancer', 'BlueSpiceWikiFarm.AccessControlStore',
				'UserFactory', 'BlueSpiceWikiFarm.InstanceGroupCreator', 'UserGroupManager'
			]
		];

		return $steps;
	}
}
