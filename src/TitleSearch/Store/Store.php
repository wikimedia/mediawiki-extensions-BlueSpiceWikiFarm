<?php
namespace BlueSpice\WikiFarm\TitleSearch\Store;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Language\Language;
use MediaWiki\Page\PageProps;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonWebAPIs\Data\TitleQueryStore\Store as TitleQueryStore;
use Wikimedia\Rdbms\ILoadBalancer;

class Store extends TitleQueryStore {

	/** @var IAccessStore */
	private $accessStore;

	/** @var InstanceStore */
	private InstanceStore $instanceStore;

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
		PageProps $pageProps, IAccessStore $accessStore, InstanceStore $instanceStore, ?array $limitToInstances = null
	) {
		parent::__construct( $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->accessStore = $accessStore;
		$this->instanceStore = $instanceStore;
		$this->limitToInstances = $limitToInstances;
	}

	/**
	 * @return Reader
	 */
	public function getReader() {
		return new Reader(
			$this->lb, $this->titleFactory, $this->language, $this->nsInfo,
			$this->pageProps, $this->accessStore, $this->instanceStore, $this->limitToInstances
		);
	}
}
