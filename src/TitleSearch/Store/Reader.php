<?php
namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\Reader as BaseReader;
use MWStake\MediaWiki\Component\DataStore\ReaderParams;
use Wikimedia\Rdbms\ILoadBalancer;

class Reader extends BaseReader {
	/** @var IAccessStore */
	private $accessStore;

	/** @var InstanceStore */
	private $instanceStore;

	/** @var InstanceEntity[]|null */
	private $limitToInstances;

	/**
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param PageProps $pageProps
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 * @param array|null $limitToInstances
	 */
	public function __construct(
		ILoadBalancer $lb, TitleFactory $titleFactory, Language $language, NamespaceInfo $nsInfo,
		PageProps $pageProps, IAccessStore $accessStore, InstanceStore $instanceStore,
		?array $limitToInstances = null
	) {
		parent::__construct( $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->accessStore = $accessStore;
		$this->instanceStore = $instanceStore;
		$this->limitToInstances = $limitToInstances;
	}

	/**
	 * @param ReaderParams $params
	 * @return PrimaryDataProvider
	 */
	public function makePrimaryDataProvider( $params ) {
		return new PrimaryDataProvider(
			$this->lb->getConnection( DB_REPLICA ), $this->getSchema(),
			$this->language, $this->nsInfo, $this->accessStore, $this->instanceStore, $this->limitToInstances
		);
	}
}
