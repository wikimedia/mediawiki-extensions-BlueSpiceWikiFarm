<?php

namespace BlueSpice\WikiFarm\MaintenanceScreens;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Html\TemplateParser;
use MediaWiki\Message\Message;

class MaintenanceScreen {

	/**
	 * @var InstanceEntity
	 */
	protected $instanceEntity;

	/**
	 * @param InstanceEntity $instanceEntity
	 */
	public function __construct( InstanceEntity $instanceEntity ) {
		$this->instanceEntity = $instanceEntity;
	}

	/**
	 * @return string
	 */
	protected function getTemplateName(): string {
		return 'maintenance';
	}

	/**
	 * @return array
	 */
	protected function getTemplateData(): array {
		$data = [ 'instance_name' => $this->instanceEntity->getDisplayName() ];
		if (
			isset( $GLOBALS['wgWikiFarmConfig_maintenanceMessage'] ) &&
			$GLOBALS['wgWikiFarmConfig_maintenanceMessage']
		) {
			$data['message'] = $GLOBALS['wgWikiFarmConfig_maintenanceMessage'];
		} else {
			$data['message'] = Message::newFromKey( 'wikifarm-maintenance-generic' )->text();
		}
		return $data;
	}

	/**
	 * @return string
	 */
	public function getHtml(): string {
		$parser = new TemplateParser( __DIR__ . '/templates' );
		return $this->wrap(
			$parser->processTemplate( $this->getTemplateName(), $this->getTemplateData() )
		);
	}

	/**
	 * @return int How often the page should be refreshed (in seconds)
	 */
	public function getRefreshInterval(): ?int {
		return 60;
	}

	/**
	 * @return string
	 */
	protected function getPageTitle(): string {
		return Message::newFromKey( 'wikifarm-maintenance-title' )->text();
	}

	/**
	 * @param string $content
	 * @return string
	 */
	protected function wrap( string $content ): string {
		$parser = new TemplateParser( __DIR__ . '/templates' );
		return $parser->processTemplate(
			'wrapper',
			[
				'content' => $content,
				'title' => $this->getPageTitle(),
				'refresh_interval' => $this->getRefreshInterval(),
				'style' => $GLOBALS['wgServer'] .
					$GLOBALS['wgScriptPath'] .
					'/extensions/BlueSpiceWikiFarm/src/MaintenanceScreens/templates/style.css'
			]
		);
	}
}
