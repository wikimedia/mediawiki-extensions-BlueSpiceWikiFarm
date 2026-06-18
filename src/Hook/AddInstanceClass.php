<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Output\Hook\OutputPageBodyAttributesHook;

class AddInstanceClass implements OutputPageBodyAttributesHook {

	/**
	 * @inheritDoc
	 */
	public function onOutputPageBodyAttributes( $out, $skin, &$bodyAttrs ): void {
		$classes = $out->getProperty( 'bodyClassName' );

		$path = FARMER_CALLED_INSTANCE;
		if ( $path === 'w' ) {
			$path = 'main';
		}
		$classes = 'bs-wikifarm-instance-' . $path;

		$bodyAttrs['class'] .= ' ' . $classes;
	}
}
