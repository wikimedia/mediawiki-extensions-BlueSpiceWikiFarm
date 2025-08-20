<?php

namespace BlueSpice\WikiFarm\Data\Teams;

use BlueSpice\WikiFarm\AccessControl\Team;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 *
	 * @param TeamManager $teamManager
	 */
	public function __construct(
		private readonly TeamManager $teamManager,
	) {
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$teams = $this->teamManager->getAllTeams();

		$matching = $this->filterByQuery( $params, $teams );

		return array_map(
			function ( $team ) {
				return new Record( (object)[
					Record::ID => $team->getId(),
					Record::NAME => $team->getName(),
					Record::DESCRIPTION => $team->getDescription(),
					Record::MEMBER_COUNT => $this->teamManager->getMemberCount( $team ),
				] );
			},
			$matching
		);
	}

	/**
	 * @param ReaderParams $params
	 * @param array $teams
	 * @return array
	 */
	private function filterByQuery( ReaderParams $params, array $teams ): array {
		if ( !$params->getQuery() ) {
			return $teams;
		}
		$filtered = [];
		foreach ( $teams as $team ) {
			if ( !$this->queryMatches( $params->getQuery(), $team ) ) {
				continue;
			}
			$filtered[] = $team;
		}

		return $filtered;
	}

	/**
	 * @param string $query
	 * @param Team $team
	 * @return bool
	 */
	private function queryMatches( string $query, Team $team ): bool {
		$query = mb_strtolower( $query );
		return str_contains( mb_strtolower( $team->getName() ), $query );
	}
}
