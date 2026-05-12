<?php

namespace BlueSpice\WikiFarm\Special;

use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\SpecialPage\SpecialPage;

class AccessManagement extends SpecialPage {

	public function __construct( private readonly Config $farmConfig ) {
		parent::__construct( 'AccessManagement', 'userrights' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		$this->setHeaders();
		$this->checkPermissions();
		$this->getOutput()->enableOOUI();
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

}
