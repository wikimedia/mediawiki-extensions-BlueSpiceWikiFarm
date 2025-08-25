<?php
namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\Reader as BaseReader;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\ILoadBalancer;

class Reader extends BaseReader {
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
		PageProps $pageProps, GlobalDatabaseQueryExecution $globalDatabaseQueryExecution ) {
		parent::__construct( $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->globalDatabaseQueryExecution = $globalDatabaseQueryExecution;
	}

	/**
	 * @param ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	public function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider(
			$this->lb->getConnection( DB_REPLICA ), $this->getSchema(),
			$this->language, $this->nsInfo, $this->globalDatabaseQueryExecution
		);
	}
}
