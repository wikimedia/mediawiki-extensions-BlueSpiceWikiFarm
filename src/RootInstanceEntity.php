<?php

namespace BlueSpice\WikiFarm;

use DateTime;

class RootInstanceEntity extends InstanceEntity {

	public function __construct() {
		parent::__construct(
			'w', 'w', 'w',
			new DateTime(), new DateTime(), static::STATUS_READY, '<root>', '', [], []
		);
	}
}
