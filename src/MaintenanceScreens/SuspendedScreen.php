<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use MediaWiki\Message\Message;

class SuspendedScreen extends MaintenanceScreen {

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'suspended';
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		$data = [];
		$data['message'] = Message::newFromKey( 'wikifarm-instance-suspended-label' )->text();
		return $data;
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey(
			'wikifarm-instance-suspended-heading', $this->instanceEntity->getDisplayName()
		)->text();
	}
}
