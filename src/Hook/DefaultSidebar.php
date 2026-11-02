<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\ProductContent;
use MediaWiki\Language\Hook\MessagesPreLoadHook;
use RuntimeException;

class DefaultSidebar implements MessagesPreLoadHook {

	/** @inheritDoc */
	public function onMessagesPreLoad( $title, &$message, $code ) {
		if ( $title !== 'Sidebar' ) {
			return;
		}

		$path = ProductContent::getPath( 'sidebar', 'wikitext' );
		$content = $path ? file_get_contents( $path ) : false;
		if ( $content === false ) {
			throw new RuntimeException( "Could not read default sidebar: $path" );
		}
		$message = $content;
	}
}
