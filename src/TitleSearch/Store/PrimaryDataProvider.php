<?php

namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\AccessControl\NullAccessStore;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Context\RequestContext;
use MediaWiki\Language\Language;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\WikiMap\WikiMap;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\PrimaryDataProvider as Base;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\TitleRecord;
use MWStake\MediaWiki\Component\DataStore\Schema;
use Wikimedia\Rdbms\IDatabase;

class PrimaryDataProvider extends Base {

	/** @var IAccessStore */
	private $accessStore;
	/** @var InstanceStore */
	private InstanceStore $instanceStore;

	/** @var InstanceEntity[]|null */
	private $limitToInstances;

	/** @var array */
	private $instanceMap = [];

	/**
	 * @param IDatabase $db
	 * @param Schema $schema
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 * @param array|null $limitToInstances
	 */
	public function __construct(
		IDatabase $db, Schema $schema, Language $language, NamespaceInfo $nsInfo,
		IAccessStore $accessStore, InstanceStore $instanceStore, ?array $limitToInstances = null
	) {
		parent::__construct( $db, $schema, $language, $nsInfo );
		$this->accessStore = $accessStore;
		$this->limitToInstances = $limitToInstances;
		$this->instanceStore = $instanceStore;
	}

	protected function appendRowToData( \stdClass $row ) {
		parent::appendRowToData( $row );
		$last = array_pop( $this->data );

		$wikiId = $last->get( TitleRecord::WIKI_ID );
		/** @var InstanceEntity $instance */
		$instance = $this->instanceMap[$wikiId] ?? null;
		if ( !$instance ) {
			return;
		}

		// Set the farm instance
		$last->set( '_instance', $instance->getPath() );
		$last->set( '_instance_display', $instance->getDisplayName() );
		$last->set( '_is_local_instance', $wikiId === WikiMap::getCurrentWikiId() );
		$last->set( '_instance_interwiki', 'wiki-' . mb_strtolower( $instance->getPath() ) );
		$this->data[] = $last;
	}

	/**
	 * @return array
	 */
	protected function getWikisToSearchIn(): array {
		if ( $this->accessStore instanceof NullAccessStore ) {
			return parent::getWikisToSearchIn();
		}
		$instances = $this->limitToInstances ?? $this->accessStore->getInstancePathsWhereUserHasRole(
			RequestContext::getMain()->getUser(), IAccessStore::ROLE_READER
		);
		$this->instanceMap = [];
		$res = [];
		foreach ( $instances as $instance ) {
			if ( is_string( $instance ) ) {
				$instance = $this->instanceStore->getInstanceByPath( $instance );
			}
			if ( !$instance ) {
				continue;
			}
			$res[] = $instance->getWikiId();
			$this->instanceMap[$instance->getWikiId()] = $instance;
		}
		return $res;
	}
}
