<?php

namespace BlueSpice\WikiFarm\Special;

use BlueSpice\WikiFarm\AccessControl\WikiAccessLookup;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Html\Html;
use MediaWiki\Html\TemplateParser;
use OOJSPlus\Special\OOJSSpecialPage;

class AccessManagement extends OOJSSpecialPage {

	public function __construct(
		private readonly Config $farmConfig,
		private readonly InstanceStore $instanceStore,
		private readonly WikiAccessLookup $wikiAccessLookup
	) {
		parent::__construct( 'AccessManagement', 'userrights' );
		$this->templateParser = new TemplateParser(
			dirname( __DIR__, 2 ) . '/resources/templates'
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function buildSkeleton() {
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addModuleStyles( [ 'ext.bluespice.wikiFarm.accessManagement.skeleton' ] );
		$skeleton = $this->templateParser->processTemplate(
			'skeleton-access-management',
			[]
		);
		$skeletonCnt = Html::openElement( 'div', [
			'id' => 'bs-accessManagement-skeleton-cnt'
		] );
		$skeletonCnt .= $skeleton;
		$skeletonCnt .= Html::closeElement( 'div' );
		$this->getOutput()->addHTML( $skeletonCnt );
	}

	/**
	 * @inheritDoc
	 */
	protected function doExecute( $subPage ) {
		$this->getOutput()->addModules( [ 'ext.bluespice.wikiFarm.accessManagement' ] );
		$this->getOutput()->addHTML(
			Html::element( 'div', [ 'id' => 'bs-access-management' ] )
		);
		$this->getOutput()->addJsConfigVars( 'wikiFarmIsRoot', FARMER_IS_ROOT_WIKI_CALL );
		$this->getOutput()->addJsConfigVars( 'wikiFarmAccessLevel', $this->getAccessLevel() );
		$this->getOutput()->addJsConfigVars(
			'wikiFarmAccessAlwaysVisible',
			FARMER_CALLED_INSTANCE === $this->farmConfig->get( 'sharedResourcesWikiPath' )
		);
	}

	/**
	 * @return string
	 */
	private function getAccessLevel(): string {
		$instance = $this->instanceStore->getInstanceByPath( FARMER_CALLED_INSTANCE );
		if ( !$instance ) {
			return 'private';
		}
		return $this->wikiAccessLookup->getAccessLevelForInstance( $instance );
	}

}
