<?php

namespace BlueSpice\WikiFarm;

use HtmlArmor;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;

class EnhancedGlobalActionsFarmManagement extends GlobalActionsFarmManagement {

	public function getPostHtml(): HtmlArmor {
		$html = Html::element( 'span', [
			'class' => 'badge'
		], Message::newFromKey( 'wikifarm-global-label' )->text() );
		return new HtmlArmor( $html );
	}
}
