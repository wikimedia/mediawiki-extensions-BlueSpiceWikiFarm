<?php

namespace BlueSpice\WikiFarm\Data\TeamMembers;

use BlueSpice\WikiFarm\AccessControl\Team;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

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

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$members = $this->teamManager->getMembers( $this->team );
		$data = [];
		foreach ( $members as $memberData ) {
			$data[] = new Record( (object)[
				Record::ID => $memberData['user']->getId(),
				Record::NAME => $memberData['user']->getName(),
				Record::DISPLAY_NAME => $memberData['user']->getRealName() ?: $memberData['user']->getName(),
				Record::EXPIRATION => $memberData['expiry'],
				Record::EXPIRATION_FORMATTED => $this->formatExpiration( $memberData['expiry'] ),
			] );
		}

		return $data;
	}

	/**
	 * @param string|null $expiration
	 * @return string
	 */
	private function formatExpiration( ?string $expiration ): string {
		return \MediaWiki\MediaWikiServices::getInstance()->getContentLanguage()->formatExpiry(
			$expiration,
			true,
			Message::newFromKey( 'wikifarm-team-members-expiration-never' )->text()
		);
	}
}
