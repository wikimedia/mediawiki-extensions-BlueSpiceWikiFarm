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
		$data = [ 'instance_name' => $this->instanceEntity->getDisplayName() ];
		$data['message'] = Message::newFromKey( 'wikifarm-instance-suspended-notice' )->text();
		return $data;
	}
}
