<?php

namespace BlueSpice\WikiFarm\Data\Teams;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\NoWriterException;

class Store implements IStore {

	/**
	 *
	 * @param TeamManager $teamManager
	 */
	public function __construct(
		private readonly TeamManager $teamManager,
	) {
	}

	/** @inheritDoc */
	public function getReader() {
		return new Reader( $this->teamManager );
	}

	/** @inheritDoc */
	public function getWriter() {
		throw new NoWriterException();
	}
}
