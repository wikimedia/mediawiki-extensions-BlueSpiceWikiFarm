<?php

namespace BlueSpice\WikiFarm\TitleSearch\Rest;

use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\TitleSearch\Store\Store;
use Config;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\TitleQueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\ILoadBalancer;

class FarmTemplateQueryStore extends TitleQueryStore {

	/** @var GlobalDatabaseQueryExecution */
	private $globalDatabaseQueryExecution;

	/** @var InstanceStore */
	private $instanceStore;

	/** @var Config */
	private $farmConfig;

	/**
	 * @param HookContainer $hookContainer
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param PageProps $pageProps
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 * @param InstanceStore $instanceStore
	 * @param Config $farmConfig
	 */
	public function __construct(
		HookContainer $hookContainer, ILoadBalancer $lb, TitleFactory $titleFactory, Language $language,
		NamespaceInfo $nsInfo, PageProps $pageProps, GlobalDatabaseQueryExecution $globalDatabaseQueryExecution,
		InstanceStore $instanceStore, Config $farmConfig
	) {
		parent::__construct( $hookContainer, $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->globalDatabaseQueryExecution = $globalDatabaseQueryExecution;
		$this->instanceStore = $instanceStore;
		$this->farmConfig = $farmConfig;
	}

	/**
	 * @inheritDoc
	 */
	protected function getStore(): IStore {
		$limitTo = [ $this->instanceStore->getCurrentInstance() ];
		if ( $this->farmConfig->get( 'useSharedResources' ) && $this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			$shared = $this->instanceStore->getInstanceByPath( $this->farmConfig->get( 'sharedResourcesWikiPath' ) );
			if ( $shared ) {
				$limitTo[] = $shared;
			}
		}
		return new Store(
			$this->lb, $this->titleFactory, $this->language, $this->nsInfo, $this->pageProps,
			$this->globalDatabaseQueryExecution, $limitTo
		);
	}

	protected function getReaderParams(): ReaderParams {
		return new ReaderParams( [
			'query' => $this->getQuery(),
			'start' => $this->getOffset(),
			'limit' => $this->getLimit(),
			'filter' => [ [
				'property' => 'namespace',
				'comparison' => 'ct',
				'type' => 'list',
				'value' => [ NS_TEMPLATE ]
			] ],
			'sort' => $this->getSort()
		] );
	}
}
