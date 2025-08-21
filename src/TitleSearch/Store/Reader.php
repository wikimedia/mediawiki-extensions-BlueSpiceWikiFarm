<?php
namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use BlueSpice\WikiFarm\InstanceEntity;
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

	/** @var InstanceEntity[]|null */
	private $limitToInstances;

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param PageProps $pageProps
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 * @param array|null $limitToInstances
	 */
	public function __construct(
		ILoadBalancer $lb, TitleFactory $titleFactory, Language $language, NamespaceInfo $nsInfo,
		PageProps $pageProps, GlobalDatabaseQueryExecution $globalDatabaseQueryExecution,
		?array $limitToInstances = null
	) {
		parent::__construct( $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->globalDatabaseQueryExecution = $globalDatabaseQueryExecution;
		$this->limitToInstances = $limitToInstances;
	}

	/**
	 * @param ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	public function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider(
			$this->lb->getConnection( DB_REPLICA ), $this->getSchema(),
			$this->language, $this->nsInfo, $this->globalDatabaseQueryExecution, $this->limitToInstances
		);
	}
}
