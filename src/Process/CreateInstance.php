<?php

namespace BlueSpice\WikiFarm\Process;

use BlueSpice\WikiFarm\Process\Step\CopyUser;
use BlueSpice\WikiFarm\Process\Step\CreateInstanceVault;
use BlueSpice\WikiFarm\Process\Step\ImportTemplate;
use BlueSpice\WikiFarm\Process\Step\InstallInstance;
use BlueSpice\WikiFarm\Process\Step\RunPostInstanceCreationTasks;
use BlueSpice\WikiFarm\Process\Step\RunUpdates;
use MWStake\MediaWiki\Component\ProcessManager\ManagedProcess;

class CreateInstance extends ManagedProcess {

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
		$steps = [
			'create-instance-vault' => [
				'class' => CreateInstanceVault::class,
				'args' => [ $this->data['instanceId'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager' ]
			],
			'install-instance' => [
				'class' => InstallInstance::class,
				'args' => [ $this->data['instanceId'], $this->data['lang'] ?? '' ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig', 'LanguageFactory' ]
			],
			'copy-user' => [
				'class' => CopyUser::class,
				'args' => [ $this->data['instanceId'], $this->data['userName'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'DBLoadBalancer' ]
			],
			'run-update' => [
				'class' => RunUpdates::class,
				'args' => [ $this->data['instanceId'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
			],
		];

		if ( $this->data['template'] ) {
			$steps['copy-template'] = [
				'class' => ImportTemplate::class,
				'args' => [ $this->data['instanceId'], $this->data['template'] ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
			];
		}

		$steps['run-post-instance-creation-commands'] = [
			'class' => RunPostInstanceCreationTasks::class,
			'args' => [ $this->data['instanceId'] ],
			'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
		];
		return $steps;
	}
}
