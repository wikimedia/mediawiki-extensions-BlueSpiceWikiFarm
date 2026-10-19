<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use MediaWiki\Message\Message;

class NotFoundScreen extends MaintenanceScreen {

	/**
	 * @inheritDoc
	 */
	protected function getTemplateName(): string {
		return 'not-found';
	}

	/**
	 * @inheritDoc
	 */
	protected function getTemplateData(): array {
		return [ 'message' => Message::newFromKey( 'wikifarm-instance-not-found-desc-label' )->text() ];
	}

	/**
	 * @inheritDoc
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey( 'wikifarm-instance-not-found-heading' )->text();
	}

	/**
	 * @inheritDoc
	 */
	public function getRefreshInterval(): ?int {
		return null;
	}
}
