<?php

namespace BlueSpice\WikiFarm\Data\WikiInstances;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\SystemInstanceEntity;
use DateTime;
use MediaWiki\Config\Config;
use MediaWiki\Context\IContextSource;
use MWStake\MediaWiki\Component\DataStore\IPrimaryDataProvider;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;

class PrimaryDataProvider implements IPrimaryDataProvider {

	/**
	 *
	 * @var array
	 */
	protected $data = [];

	/**
	 *
	 * @var InstanceStore
	 */
	protected $instanceStore = null;

	/**
	 *
	 * @var Config
	 */
	protected $farmConfig = null;

	/**
	 *
	 * @var Config
	 */
	protected $mainConfig = null;

	/**
	 *
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 * @param IContextSource $context
	 */
	public function __construct(
		InstanceStore $instanceStore, Config $farmConfig, Config $mainConfig,
		private readonly IContextSource $context
	) {
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
		$this->mainConfig = $mainConfig;
	}

	/**
	 * @param ReaderParams $params
	 * @return array|\MWStake\MediaWiki\Component\DataStore\Record[]
	 */
	public function makeData( $params ) {
		$ids = $this->instanceStore->getInstanceIds();
		foreach ( $ids as $id ) {
			$instance = $this->instanceStore->getInstanceById( $id );
			if ( !$instance ) {
				continue;
			}

			if ( !$params->getQuery() || $this->queryMatches( $params->getQuery(), $instance ) ) {
				$this->appendToData( $instance );
			}
		}

		return $this->data;
	}

	/**
	 *
	 * @param InstanceEntity|null $instance
	 */
	protected function appendToData( ?InstanceEntity $instance ) {
		if (
			!$instance ||
			$instance->getStatus() === InstanceEntity::STATUS_ARCHIVED
		) {
			return;
		}

		$server = $this->mainConfig->get( 'Server' );
		$scriptPath = $instance->getScriptPath( $this->farmConfig );
		$fullUrl = $server . $scriptPath;
		$data = [
			Record::PATH => $instance->getPath(),
			Record::MTIME => $this->formatTimestamp( $instance->getUpdated() ),
			Record::CTIME => $this->formatTimestamp( $instance->getCreated() ),
			Record::TITLE => $instance->getDisplayName(),
			Record::FULLURL => $fullUrl,
			Record::IS_COMPLETE => $instance->getStatus() !== InstanceEntity::STATUS_INIT &&
				$instance->getStatus() !== InstanceEntity::STATUS_INSTALLED,
			Record::SUSPENDED => $instance->getStatus() === InstanceEntity::STATUS_SUSPENDED,
			Record::NOTSEARCHABLE => $instance->getMetadata()['notsearchable'] ?? false,
			Record::META_GROUP => '',
			Record::IS_SYSTEM => $instance instanceof SystemInstanceEntity,
		];

		$data['meta_keywords'] = [];
		$data['meta_group'] = '';
		$data['meta_desc'] = '';
		foreach ( $instance->getMetadata() as $key => $value ) {
			if ( $key === 'notsearchable' ) {
				continue;
			}
			$data['meta_' . $key] = $value;
		}

		$this->data[] = new Record( (object)$data );
	}

	/**
	 * @param string $query
	 * @param InstanceEntity $instance
	 * @return bool
	 */
	private function queryMatches( string $query, InstanceEntity $instance ): bool {
		$name = $instance->getDisplayName();
		$path = $instance->getPath();

		$name = mb_strtolower( $name );
		$path = mb_strtolower( $path );
		$query = mb_strtolower( $query );
		return str_contains( $name, $query ) || str_contains( $path, $query );
	}

	/**
	 * @param DateTime $dateTime
	 * @return string
	 */
	private function formatTimestamp( DateTime $dateTime ): string {
		return $this->context->getLanguage()->userTimeAndDate(
			$dateTime->getTimestamp(), $this->context->getUser(), [ 'timecorrection' => true ]
		);
	}
}
