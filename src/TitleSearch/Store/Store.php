<?php
namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\Store as TitleQueryStore;
use Wikimedia\Rdbms\ILoadBalancer;

class Store extends TitleQueryStore {

	/** @var GlobalDatabaseQueryExecution */
	private $globalDatabaseQueryExecution;

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param PageProps $pageProps
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 */
	public function __construct(
		ILoadBalancer $lb, TitleFactory $titleFactory, Language $language, NamespaceInfo $nsInfo,
		PageProps $pageProps, GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	) {
		parent::__construct( $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->globalDatabaseQueryExecution = $globalDatabaseQueryExecution;
	}

	/**
	 * @return Reader
	 */
	public function getReader() {
		return new Reader(
			$this->lb, $this->titleFactory, $this->language, $this->nsInfo,
			$this->pageProps, $this->globalDatabaseQueryExecution
		);
	}
}
