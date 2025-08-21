<?php

namespace BlueSpice\WikiFarm\Process;

use BlueSpice\WikiFarm\Process\Step\RunPostInstanceDeletionCommands;
use BlueSpice\WikiFarm\Process\Step\RunPreInstanceDeletionCommands;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;

class ArchiveInstance extends ManagedProcess {

	/** @var array */
	protected $data;

	/**
	 * @param array $data
	 */
	public function __construct( array $data ) {
		$this->data = $data;

		parent::__construct( [], 3600 );
	}

	/**
	 * @return array[]
	 */
	public function getSteps(): array {
		return [
			'run-pre-instance-deletion-commands' => [
				'class' => RunPreInstanceDeletionCommands::class,
				'args' => [ $this->data['instanceId'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
			],
			'archive-instance' => [
				'class' => Step\ArchiveInstance::class,
				'args' => [ $this->data['instanceId'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
			],
			'run-post-instance-deletion-commands' => [
				'class' => RunPostInstanceDeletionCommands::class,
				'args' => [ $this->data['instanceId'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
			],
		];
	}
}
