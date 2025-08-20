<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Message\Message;

class CreationFailure extends MaintenanceScreen {

	/**
	 * @var string
	 */
	private $pid;

	/**
	 * @param InstanceEntity $instanceEntity
	 * @param string $pid
	 */
	public function __construct( InstanceEntity $instanceEntity, string $pid ) {
		parent::__construct( $instanceEntity );
		$this->pid = $pid;
	}

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'creation-fail';
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		return [
			'instance' => $this->instanceEntity->getDisplayName(),
			'main_label' => Message::newFromKey( 'wikifarm-creation-failure-main-label' )->text(),
			'desc_message' => Message::newFromKey( 'wikifarm-creation-failure-desc' )->text(),
			'process_id' => Message::newFromKey( 'wikifarm-creation-failure-process', $this->pid )->text(),
		];
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey( 'wikifarm-creation-failure-main-label' )->text();
	}
}
