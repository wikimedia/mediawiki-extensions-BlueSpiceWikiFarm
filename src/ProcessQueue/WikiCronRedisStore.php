<?php

namespace BlueSpice\WikiFarm\ProcessQueue;

use MWStake\MediaWiki\Component\WikiCron\Store\RedisStore;

class WikiCronRedisStore extends RedisStore {

	/** @var bool */
	private bool $isRoot;

	/** @var string|null */
	private ?string $forInstance;

	/**
	 * @param array $params
	 */
	public function __construct( array $params ) {
		parent::__construct( $params );
		$this->isRoot = $params['isRoot'] ?? false;
		$this->forInstance = $params['forInstance'] ?? null;
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
		return parent::getProcessAdditionalArgs( $name, $wikiId );
	}
}
