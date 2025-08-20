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
			'main_label' => Message::newFromKey( 'wikifarm-creation-progress-main-label' )->text(),
			'desc_message' => Message::newFromKey( 'wikifarm-creation-desc' )->text(),
		];
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey( 'wikifarm-maintenance-creation-title' )->text();
	}
}
