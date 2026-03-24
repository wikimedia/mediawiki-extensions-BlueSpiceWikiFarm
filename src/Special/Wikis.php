<?php

namespace BlueSpice\WikiFarm\Special;

use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;

class Wikis extends OOJSGridSpecialPage {

	public function __construct() {
		parent::__construct( 'Wikis' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.instances.special' ] );
		$this->getOutput()->addHTML(
			Html::element( 'div', [ 'id' => 'bs-wikifarm-user-instances' ] )
		);
	}

}
