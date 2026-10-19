<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use MediaWiki\Message\Message;

class NonExistingInstanceScreen extends MaintenanceScreen {

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'non-existing';
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		$data = [];
		$instancePath = $this->instanceEntity->getPath();
		$data['message'] = Message::newFromKey( 'wikifarm-non-existing-label', $instancePath )->text();
		$managementUrl = $GLOBALS['wgWikiFarmConfig_farmManagementUrl'] ?? '';
		$data['creation_url'] = $managementUrl . '/_create?path=' . $this->instanceEntity->getId();
		$data['creation_message'] = Message::newFromKey( 'wikifarm-non-existing-create-button-label' )->text();
		return $data;
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey( 'wikifarm-non-existing-title' )->text();
	}
}
