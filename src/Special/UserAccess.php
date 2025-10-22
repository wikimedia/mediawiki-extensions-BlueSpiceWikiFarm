<?php

namespace BlueSpice\WikiFarm\Special;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;
use OOUI\MessageWidget;

class UserAccess extends OOJSGridSpecialPage {

	public function __construct( private readonly Config $farmConfig ) {
		parent::__construct( 'UserAccess', 'userrights' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		$this->addScopeNotice();
		parent::execute( $subPage );
		$this->getOutput()->addModules( [ "ext.bluespice.wikiFarm.access" ] );
		$this->getOutput()->addHTML(
			Html::element( 'div', [ 'id' => 'bs-wiki-access' ] )
		);
		$this->getOutput()->addJsConfigVars( 'wikiFarmIsRoot', FARMER_IS_ROOT_WIKI_CALL );
		$this->getOutput()->addJsConfigVars( 'wikiFarmAccessLevel', $this->getConfig()->get( 'WikiFarmAccessLevel' ) );
		$this->getOutput()->addJsConfigVars(
			'wikiFarmAccessAlwaysVisible',
			FARMER_CALLED_INSTANCE === $this->farmConfig->get( 'sharedResourcesWikiPath' )
		);
	}

	/**
	 * @return void
	 */
	private function addScopeNotice() {
		$this->getOutput()->enableOOUI();
		$msg = FARMER_IS_ROOT_WIKI_CALL ?
			$this->msg( 'wikifarm-role-scope-notice-global' ) :
			$this->msg( 'wikifarm-role-scope-notice-local' );
		$this->getOutput()->addHTML(
			new MessageWidget(
				[
					'type' => 'info',
					'label' => $msg->text()
				]
			)
		);
	}

}
