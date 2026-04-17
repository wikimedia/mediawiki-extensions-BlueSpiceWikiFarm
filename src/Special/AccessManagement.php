<?php

namespace BlueSpice\WikiFarm\Special;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;
use OOUI\MessageWidget;

class AccessManagement extends SpecialPage {

	public function __construct( private readonly Config $farmConfig ) {
		parent::__construct( 'AccessManagement' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'userrights';
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->getOutput()->enableOOUI();
		$this->addScopeNotice();
		$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.accessManagement' ] );
		$this->getOutput()->addHTML(
			Html::element( 'div', [ 'id' => 'bs-access-management' ] )
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
