<?php

namespace BlueSpice\WikiFarm\Process;

use BlueSpice\WikiFarm\Process\Step\CopyInstanceData;

class CloneInstance extends CreateInstance {

	/**
	 * @return array
	 */
	public function getSteps(): array {
		$steps = parent::getSteps();
		// Insert step after 'install-instance'
		$indexOf = array_search( 'install-instance', array_keys( $steps ) );
		return array_merge(
			array_slice( $steps, 0, $indexOf + 1 ),
			[
				'copy-instance-data' => [
					'class' => CopyInstanceData::class,
					'args' => [ $this->data['instanceId'], $this->data['sourceInstanceId'] ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'DBLoadBalancer' ]
				]
			],
			array_slice( $steps, $indexOf + 1 )
		);
	}
}
