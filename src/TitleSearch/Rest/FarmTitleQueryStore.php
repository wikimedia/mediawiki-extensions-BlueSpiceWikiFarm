<?php

namespace BlueSpice\WikiFarm\TitleSearch\Rest;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\InstanceStore;
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

	/** @var IAccessStore */
	private $accessStore;
	/** @var InstanceStore */
	private InstanceStore $instanceStore;

	/**
	 * @param HookContainer $hookContainer
	 * @param ILoadBalancer $lb
	 * @param TitleFactory $titleFactory
	 * @param Language $language
	 * @param NamespaceInfo $nsInfo
	 * @param PageProps $pageProps
	 * @param IAccessStore $accessStore
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		HookContainer $hookContainer, ILoadBalancer $lb, TitleFactory $titleFactory, Language $language,
		NamespaceInfo $nsInfo, PageProps $pageProps, IAccessStore $accessStore, InstanceStore $instanceStore
	) {
		parent::__construct( $hookContainer, $lb, $titleFactory, $language, $nsInfo, $pageProps );
		$this->instanceStore = $instanceStore;
		$this->accessStore = $accessStore;
	}

	/**
	 * @inheritDoc
	 */
	protected function getStore(): IStore {
		return new Store(
			$this->lb, $this->titleFactory, $this->language, $this->nsInfo, $this->pageProps,
			$this->accessStore, $this->instanceStore
		);
	}
}
