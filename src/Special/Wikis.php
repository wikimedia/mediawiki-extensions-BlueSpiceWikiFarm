<?php

namespace BlueSpice\WikiFarm\Special;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;

class Wikis extends OOJSGridSpecialPage {

	/**
	 * @param InstanceCountLimiter $countLimiter
	 */
	public function __construct( private readonly InstanceCountLimiter $countLimiter ) {
		parent::__construct( 'Wikis' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.wikis.special' ] );
		$wikiCanBeCreated = $this->countLimiter->canCreate();
		$this->getOutput()->addHTML(
			Html::element( 'div', [
				'id' => 'bs-wikifarm-wikis',
				'data-creation' => $wikiCanBeCreated
			] )
		);
	}

}
