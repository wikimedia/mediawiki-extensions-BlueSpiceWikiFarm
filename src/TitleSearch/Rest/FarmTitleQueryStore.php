<?php

namespace BlueSpice\WikiFarm\TitleSearch\Rest;

use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use BlueSpice\WikiFarm\TitleSearch\Store\Store;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Rest\TitleQueryStore;
use MWStake\MediaWiki\Component\DataStore\IStore;
use Wikimedia\Rdbms\ILoadBalancer;

class FarmTitleQueryStore extends TitleQueryStore {

	/** @var GlobalDatabaseQueryExecution */
	private $globalDatabaseQueryExecution;

	/**
	 * @param HookContainer $hookContainer
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param PageProps $pageProps
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 */
	public function __construct(
		HookContainer $hookContainer, ILoadBalancer $lb, TitleFactory $titleFactory, Language $language,
		NamespaceInfo $nsInfo, PageProps $pageProps, GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	) {
		parent::__construct( $hookContainer, $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->globalDatabaseQueryExecution = $globalDatabaseQueryExecution;
	}

	/**
	 * @inheritDoc
	 */
	protected function getStore(): IStore {
		return new Store(
			$this->lb, $this->titleFactory, $this->language, $this->nsInfo, $this->pageProps,
			$this->globalDatabaseQueryExecution
		);
	}
}
