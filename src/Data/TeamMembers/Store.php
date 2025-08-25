<?php

namespace BlueSpice\WikiFarm\Data\TeamMembers;

use BlueSpice\WikiFarm\AccessControl\Team;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store implements IStore {

	/**
	 *
	 * @param TeamManager $teamManager
	 * @param Team $team
	 */
	public function __construct(
		private readonly TeamManager $teamManager,
		private readonly Team $team
	) {
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->teamManager, $this->team );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
