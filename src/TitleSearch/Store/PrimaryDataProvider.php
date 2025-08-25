<?php

namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use MediaWiki\Language\Language;
use MediaWiki\Title\NamespaceInfo;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\PrimaryDataProvider as Base;
use MWStake\MediaWiki\Component\DataStore\Schema;
use Wikimedia\Rdbms\IDatabase;

class PrimaryDataProvider extends Base {

	/** @var GlobalDatabaseQueryExecution */
	private $globalDatabaseQueryExecution;

	/**
	 * @param IDatabase $db
	 * @param Schema $schema
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 */
	public function __construct(
		IDatabase $db, Schema $schema, Language $language, NamespaceInfo $nsInfo,
		GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	) {
		parent::__construct( $db, $schema, $language, $nsInfo );
		$this->globalDatabaseQueryExecution = $globalDatabaseQueryExecution;
	}

	/**
	 * @inheritDoc
	 */
	public function makeData( $params ) {
		$this->data = [];

		$res = $this->globalDatabaseQueryExecution->select(
			$this->getTableNames(),
			$this->getFields(),
			$this->makePreFilterConds( $params ),
			__METHOD__,
			$this->makePreOptionConds( $params ),
			$this->getJoinConds( $params )
		);
		foreach ( $res as $row ) {
			$this->appendRowToData( $row );
			// Get last item of `$this->data`
			$last = end( $this->data );
			// Set the farm instance
			$last->set( '_instance', $row->_instance ?? '' );
			$last->set( '_instance_display', $row->_instance_display ?? $last->get( '_instance' ) );
			$last->set( '_is_local_instance', $row->_is_local_instance ?? true );
			$last->set( '_instance_interwiki', $row->_instance_interwiki ?? '' );
		}

		return $this->data;
	}
}
