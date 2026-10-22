<?php

namespace BlueSpice\WikiFarm\Hook;

use MediaWiki\Language\Hook\MessagesPreLoadHook;
use RuntimeException;

class DefaultSidebar implements MessagesPreLoadHook {

	/** @inheritDoc */
	public function onMessagesPreLoad( $title, &$message, $code ) {
		if ( $title !== 'Sidebar' ) {
			return;
		}

		$path = dirname( __DIR__, 2 ) . '/content/sidebar/farm.wikitext';
		$content = file_get_contents( $path );
		if ( $content === false ) {
			throw new RuntimeException( "Could not read default sidebar: $path" );
		}
		$message = $content;
	}
}
