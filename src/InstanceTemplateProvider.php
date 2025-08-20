<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Message\Message;

class InstanceTemplateProvider {
	/**
	 * @param \Config $mainConfig
	 */
	public function __construct(
		private readonly \Config $mainConfig
	) {
	}

	/**
	 * @return string[]
	 */
	public function getTemplates(): array {
		$templates = $this->mainConfig->get( 'WikiFarmInstanceTemplates' ) ?? [];
		$data = [];
		foreach ( $templates as $key => $template ) {
			if ( !file_exists( $template['source' ] ) ) {
				throw new \RuntimeException(
					__METHOD__ . ' - Template source file does not exist: ' . $template['source' ]
				);
			}
			$data[ $key ] = [
				'label' => $this->stringOrMessage( $template['label'] ),
				'description' => $this->stringOrMessage( $template['description'] ),
			];
		}
		return $data;
	}

	/**
	 * @param string $key
	 * @return string
	 */
	public function getTemplateSource( string $key ): string {
		$templates = $this->mainConfig->get( 'WikiFarmInstanceTemplates' ) ?? [];
		if ( !isset( $templates[ $key ] ) ) {
			throw new \RuntimeException(
				__METHOD__ . ' - Template not found: ' . $key
			);
		}
		return $templates[$key]['source'] ?? '';
	}

	/**
	 * @param string $key
	 * @return string
	 */
	private function stringOrMessage( string $key ): string {
		$msg = Message::newFromKey( $key );
		if ( $msg->exists() ) {
			return $msg->text();
		}
		return $key;
	}
}
