<?php

namespace BlueSpice\WikiFarm\Special;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MediaWiki\Html\Html;
use OOJSPlus\Special\OOJSGridSpecialPage;
use OOUI\MessageWidget;

class WikiTeams extends OOJSGridSpecialPage {

	/**
	 */
	public function __construct(
		private readonly TeamManager $manager
	) {
		parent::__construct( 'WikiTeams', 'wikiadmin' );
	}

	/**
	 * @param string $subPage
	 * @return void
	 */
	public function execute( $subPage ) {
		if ( $subPage ) {
			$this->outputTeam( $subPage );
			parent::execute( $subPage );
			$this->getOutput()->setPageTitle( $this->msg( 'wikifarm-team-details-title' )->text() );
			return;
		}
		$this->addTeamScopeNotice();
		parent::execute( $subPage );
		$this->getOutput()->addModules( [ "ext.bluespice.wikiFarm.teams.grid" ] );
		$this->getOutput()->addHTML(
			Html::element( 'div', [ 'id' => 'bs-wiki-teams-grid' ] )
		);
	}

	/**
	 * @param string $teamName
	 * @return void
	 */
	protected function outputTeam( string $teamName ) {
		try {
			$team = $this->manager->getTeam( $teamName );
		} catch ( \Throwable $ex ) {
			$this->getOutput()->enableOOUI();
			$this->getOutput()->addHTML(
				new MessageWidget(
					[
						'type' => 'error',
						'label' => $this->msg( 'wikifarm-team-not-found', $teamName )->text()
					]
				)
			);
			return;
		}
		$this->addNoticeIfNecessary();
		$this->getOutput()->addModules( [ "ext.bluespice.wikiFarm.teams.details" ] );
		$this->getOutput()->addHTML(
			Html::element( 'div', [
				'id' => 'bs-wiki-team-details',
				'data-team' => json_encode( $team ),
			] )
		);
	}

	/**
	 * @return void
	 */
	protected function addNoticeIfNecessary() {
		$request = $this->getRequest();
		if ( $request->getBool( 'created' ) ) {
			$this->getOutput()->enableOOUI();
			$this->getOutput()->addHTML(
				new MessageWidget(
					[
						'type' => 'success',
						'label' => $this->msg( 'wikifarm-team-created' )->text()
					]
				)
			);
			$this->getOutput()->addHTML( '<hr style="margin: 10px 0">' );
		}
	}

	private function addTeamScopeNotice() {
		$this->getOutput()->enableOOUI();
		$this->getOutput()->addHTML(
			new MessageWidget(
				[
					'type' => 'info',
					'label' => $this->msg( 'wikifarm-team-scope-notice' )->text()
				]
			)
		);
	}

}
