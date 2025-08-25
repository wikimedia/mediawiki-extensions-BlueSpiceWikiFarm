<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Message\Message;

abstract class ProcessProgressScreen extends MaintenanceScreen {

	/**
	 * @var InstanceEntity
	 */
	protected $instanceEntity;

	/**
	 * @var array
	 */
	protected $progress;

	/**
	 * @param InstanceEntity $instanceEntity
	 * @param array $progress
	 */
	public function __construct( InstanceEntity $instanceEntity, array $progress ) {
		parent::__construct( $instanceEntity );
		$this->progress = $progress;
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		return [
			'instance' => $this->instanceEntity->getDisplayName(),
			'steps' => $this->getStepsForTemplate(),
			'percent' => $this->getCompletionPercent()
		];
	}

	/**
	 * @return array
	 */
	protected function getStepsForTemplate(): array {
		$data = [];
		foreach ( $this->progress as $step => $status ) {
			$step = str_replace( '-', '', $step );
			$data[] = [
				'key' => $step,
				'label' => Message::newFromKey( "wikifarm-action-$step-description" )->text(),
				'status' => $status
			];
		}

		return $data;
	}

	/**
	 * @return int
	 */
	protected function getCompletionPercent(): int {
		$total = count( $this->progress );
		$completed = 0;
		foreach ( $this->progress as $status ) {
			if ( $status === 'completed' ) {
				$completed++;
			}
		}
		return $total > 0 ? round( ( $completed / $total ) * 100 ) : 0;
	}

	/**
	 * @return int|null
	 */
	public function getRefreshInterval(): ?int {
		return 2;
	}
}
