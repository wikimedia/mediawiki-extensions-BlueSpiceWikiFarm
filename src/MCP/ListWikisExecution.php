<?php

namespace BlueSpice\WikiFarm\MCP;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\WikiMCPTools\IMcpTool;
use MediaWiki\Extension\WikiMCPTools\IMcpToolExecutor;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;

class ListWikisExecution implements IMcpToolExecutor {

	/**
	 * @param FarmWikiMap $wikiMap
	 * @param IAccessStore $accessStore
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly FarmWikiMap $wikiMap,
		private readonly IAccessStore $accessStore,
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @param IMcpTool $tool
	 * @param array $input
	 * @return array
	 */
	public function execute( IMcpTool $tool, array $input ): array {
		$user = $this->getUser();
		$currentWikiId = $this->getCurrentWikiId();
		$wikis = [];
		foreach ( $this->wikiMap->getMap() as $wikiId => $instance ) {
			if ( !$instance->isActive() ) {
				continue;
			}
			if ( $this->farmConfig->get( 'shareUsers' ) ) {
				if ( !$this->accessStore->userHasRoleOnInstance( $user, IAccessStore::ROLE_READER, $instance ) ) {
					continue;
				}
			} elseif ( (string)$wikiId !== $currentWikiId ) {
				// Without shared users, each wiki has its own accounts, so the user is only known on this wiki
				continue;
			}
			$wikis[] = $this->getWikiData( (string)$wikiId, $instance );
		}

		return [ 'wikis' => $wikis ];
	}

	/**
	 * @param string $wikiId
	 * @param InstanceEntity $instance
	 * @return array
	 */
	private function getWikiData( string $wikiId, InstanceEntity $instance ): array {
		return [
			'wiki_id' => $wikiId,
			'sitename' => $instance->getDisplayName(),
			'description' => (string)( $instance->getMetadata()['desc'] ?? '' ),
			'path' => $instance->getPath(),
			'url' => $instance->getUrl( $this->farmConfig ),
			'is_main' => $instance instanceof RootInstanceEntity
		];
	}

	/**
	 * @return UserIdentity
	 */
	protected function getUser(): UserIdentity {
		return RequestContext::getMain()->getUser();
	}

	/**
	 * @return string
	 */
	protected function getCurrentWikiId(): string {
		return WikiMap::getCurrentWikiId();
	}
}
