<?php

namespace BlueSpice\WikiFarm\Data\TeamMembers;

use BlueSpice\WikiFarm\AccessControl\Team;
use BlueSpice\WikiFarm\AccessControl\TeamManager;

class Reader extends \MWStake\MediaWiki\Component\DataStore\Reader {

	/**
	 * @param TeamManager $teamManager
	 */
	public function __construct(
		private readonly TeamManager $teamManager,
		private readonly Team $team,
	) {
		parent::__construct();
	}

	/**
	 * @param array $params
	 * @return PrimaryDataProvider
	 */
	protected function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider( $this->teamManager, $this->team );
	}

	protected function makeSecondaryDataProvider() {
		return null;
	}

	/**
	 * @return Schema
	 */
	public function getSchema() {
		return new Schema();
	}
}
