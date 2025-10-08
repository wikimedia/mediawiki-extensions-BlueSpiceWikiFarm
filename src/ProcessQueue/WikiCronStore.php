<?php

namespace BlueSpice\WikiFarm\ProcessQueue;

use MediaWiki\Config\Config;
use MWStake\MediaWiki\Component\WikiCron\LocalDatabaseStore;
use Wikimedia\Rdbms\ILoadBalancer;

class WikiCronStore extends LocalDatabaseStore {

	use GlobalProcessDatabaseTrait;

	/**
	 * @param ILoadBalancer $lb
	 * @param Config $farmConfig
	 * @param string $forInstance
	 * @param bool $isRoot
	 */
	public function __construct(
		ILoadBalancer $lb,
		protected readonly Config $farmConfig,
		private readonly string $forInstance,
		private readonly bool $isRoot
	) {
		parent::__construct( $lb );
	}

	/**
	 * @return string
	 */
	public function getWikiId(): string {
		if ( $this->isRoot || $this->forInstance === 'w' ) {
			return 'w';
		}
		return $this->forInstance;
	}

	/**
	 * @inheritDoc
	 */
	public function getProcessAdditionalArgs( string $name, ?string $wikiId = null ): array {
		if ( $wikiId !== 'w' ) {
			return [
				'sfr' => $wikiId,
				'farm-quiet' => true,
			];
		}
		return [];
	}
}
