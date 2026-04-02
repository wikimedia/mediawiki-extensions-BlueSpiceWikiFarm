<?php

namespace BlueSpice\WikiFarm;

use HtmlArmor;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;

class EnhancedGlobalActionsAdministration extends GlobalActionsAdministration {

	public function getPostHtml(): HtmlArmor {
		$html = Html::element( 'span', [
			'class' => 'badge rounded-pill text-bg-secondary'
		], Message::newFromKey( 'wikifarm-global-label' )->text() );
		return new HtmlArmor( $html );
	}
}
