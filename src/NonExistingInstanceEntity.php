<?php

namespace BlueSpice\WikiFarm;

use DateTime;

class NonExistingInstanceEntity extends InstanceEntity {

	/**
	 * @param string $id
	 */
	public function __construct( string $id ) {
		parent::__construct(
			$id, $id, $id, new DateTime(), new DateTime(), static::STATUS_INIT, '<root>', '', [], []
		);
	}
}
