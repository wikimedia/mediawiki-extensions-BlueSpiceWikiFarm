<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use MediaWiki\Message\Message;

class CreationProcessProgress extends ProcessProgressScreen {

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'creation';
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		return parent::getTemplateData() + [
			'desc_message' => Message::newFromKey( 'wikifarm-creation-desc' )->text(),
		];
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		$instanceName = $this->instanceEntity->getDisplayName();
		return Message::newFromKey( 'wikifarm-maintenance-creation-heading-label', $instanceName )->text();
	}
}
