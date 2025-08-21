<?php

namespace BlueSpice\WikiFarm\Api;

use BlueSpice\WikiFarm\InstanceStore;
use BSApiExtJSStoreBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\MediaWikiServices;
use stdClass;

class GroupStore extends BSApiExtJSStoreBase {

	/** @var array */
	protected $results = [];

	/**
	 *
	 * @var InstanceStore
	 */
	protected $instanceStore;

	/**
	 * @inheritDoc
	 */
	public function __construct( ApiMain $mainModule, $moduleName, $modulePrefix = '' ) {
		parent::__construct( $mainModule, $moduleName, $modulePrefix );
		$this->instanceStore = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceStore' );
	}

	/**
	 *
	 * @param string $query
	 * @return stdClass[]
	 */
	protected function makeData( $query = '' ) {
		foreach ( $this->instanceStore->getInstanceIds() as $id ) {
			$instance = $this->instanceStore->getInstanceById( $id );
			$this->appendResults( $query, $instance->getMetadata() );
		}

		return array_values( $this->results );
	}

	/**
	 * @param string $q
	 * @param array $metadata
	 * @return void
	 */
	protected function appendResults( string $q, array $metadata ) {
		if ( isset( $metadata['group'] ) && $metadata['group'] ) {
			$this->results[$metadata['group']] = (object)[
				'text' => $metadata['group']
			];
		}
	}
}
