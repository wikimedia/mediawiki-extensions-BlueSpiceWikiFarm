<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\ForeignRequestExecution;
use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\Hook\APIAfterExecuteHook;
use MediaWiki\Config\Config;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\TemplateData\Api\ApiTemplateData;
use MediaWiki\Hook\BeforePageDisplayHook;
use MediaWiki\Hook\BeforeParserFetchTemplateRevisionRecordHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Request\WebRequest;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\NamespaceInfo;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use OOUI\MessageWidget;

class HandleSharedResources implements
	BeforeParserFetchTemplateRevisionRecordHook,
	SkinTemplateNavigation__UniversalHook,
	BlueSpiceDiscoveryTemplateDataProviderAfterInit,
	BeforePageDisplayHook,
	APIAfterExecuteHook
{

	/**
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 * @param TitleFactory $titleFactory
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 * @param InstanceStore $instanceStore
	 * @param IAccessStore $accessStore
	 * @param ForeignRequestExecution $foreignRequestExecution
	 * @param NamespaceInfo $namespaceInfo
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly Config $mainConfig,
		private readonly TitleFactory $titleFactory,
		private readonly GlobalDatabaseQueryExecution $globalDatabaseQueryExecution,
		private readonly InstanceStore $instanceStore,
		private readonly IAccessStore $accessStore,
		private readonly ForeignRequestExecution $foreignRequestExecution,
		private readonly NamespaceInfo $namespaceInfo
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeParserFetchTemplateRevisionRecord(
		?LinkTarget $contextTitle, LinkTarget $title, bool &$skip, ?RevisionRecord &$revRecord
	) {
		if (
			!$this->farmConfig->get( 'useSharedResources' ) ||
			FARMER_CALLED_INSTANCE === $this->farmConfig->get( 'sharedResourcesWikiPath' )
		) {
			return;
		}
		if ( $title->getNamespace() !== NS_TEMPLATE ) {
			return;
		}
		$targetTitle = $this->titleFactory->castFromLinkTarget( $title );
		if ( $targetTitle->exists() ) {
			// Template exists locally, bail out
			return;
		}
		$sharedRevision = $this->getSharedRevision( $targetTitle );
		if ( $sharedRevision ) {
			$revRecord = $sharedRevision;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onBlueSpiceDiscoveryTemplateDataProviderAfterInit( $registry ): void {
		$registry->register( 'actions_secondary', 'ca-shared-promote' );
	}

	/**
	 * @inheritDoc
	 */
	public function onSkinTemplateNavigation__Universal( $sktemplate, &$links ): void {
		if ( !$this->isRelevantPage( $sktemplate->getTitle(), true ) ) {
			return;
		}
		$shared = $this->getSharedInstance();
		if ( !$shared ) {
			return;
		}
		if ( $this->existsOnShared( $sktemplate->getTitle(), $shared ) ) {
			return;
		}

		if ( !$this->isEligibleForPromotion( $sktemplate->getUser(), $shared ) ) {
			return;
		}
		$links['actions']['promote-shared'] = [
			'text' => $sktemplate->getContext()->msg( 'wikifarm-promote-to-shared-ca' )->text(),
			'href' => '#',
			'class' => false,
			'id' => 'ca-shared-promote',
			'position' => 12,
		];
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->isRelevantPage( $out->getTitle() ) ) {
			return;
		}
		$shared = $this->getSharedInstance();
		if ( !$shared ) {
			return;
		}
		if ( !$this->existsOnShared( $out->getTitle(), $shared ) ) {
			return;
		}
		$out->enableOOUI();
		$out->addHTML( ( new MessageWidget( [
			'label' => $out->msg( 'wikifarm-shared-resources-exists-on-shared' ),
		] ) )->toString() );
	}

	/**
	 * @inheritDoc
	 */
	public function onAPIAfterExecute( $module ) {
		if ( $module instanceof ApiTemplateData ) {
			$this->handleTemplateData( $module );
		}
		if ( $module instanceof ApiQuery ) {
			$this->handleImageInfo( $module );
		}
	}

	/**
	 * @param ApiTemplateData $module
	 * @return void
	 */
	private function handleTemplateData( ApiTemplateData $module ) {
		$sharedInstance = $this->getSharedInstance();
		if ( !$sharedInstance ) {
			return;
		}
		$foreignPages = $this->getForeignTemplateData( $module->getRequest(), $sharedInstance );

		$pages = $module->getResult()->getResultData( 'pages' );
		foreach ( $pages as $id => $page ) {
			if ( isset( $page['missing' ] ) ) {
				// Page doesn't exist locally
				if ( isset( $foreignPages[$page['title'] ] ) ) {
					$module->getResult()->removeValue( 'pages', $id );
					// Exists on shared
					$module->getResult()->addValue( 'pages', $id, $foreignPages[$page['title']] );
				}
			}
		}
	}

	/**
	 * @param ApiQuery $module
	 * @return void
	 */
	private function handleImageInfo( ApiQuery $module ) {
		// Replace file namespace in arbitrary language with canonical - on shared resource wiki,
		// to work well with other instances that may be in any language
		if ( FARMER_CALLED_INSTANCE !== $this->farmConfig->get( 'sharedResourcesWikiPath' ) ) {
			return;
		}
		$fileCanonical = $this->namespaceInfo->getCanonicalName( NS_FILE );
		$result = $module->getResult();
		$pages = $result->getResultData( [ 'query', 'pages' ] ) ?? [];
		foreach ( $pages as $index => $page ) {
			if ( !isset( $page['imagerepository' ] ) || $page['ns'] !== NS_FILE ) {
				// Not image info response
				continue;
			}
			// Convert title to canonical namespace
			$title = $this->titleFactory->newFromText( $page['title'], NS_FILE );
			if ( $title->getNsText() === $fileCanonical ) {
				continue;
			}
			$result->removeValue( [ 'query', 'pages', $index ], 'title' );
			$result->addValue( [ 'query', 'pages', $index ], 'title', $fileCanonical . ':' . $title->getDBkey() );
		}
	}

	/**
	 * @param Title|null $title
	 * @param bool $mustExist
	 * @return bool
	 */
	private function isRelevantPage( ?Title $title, bool $mustExist = false ): bool {
		if ( !$this->farmConfig->get( 'useSharedResources' ) || !$this->farmConfig->get( 'useGlobalAccessControl' ) ) {
			return false;
		}

		if ( !$title || ( $mustExist && !$title->exists() ) ) {
			return false;
		}

		$ns = $title->getNamespace();
		if ( !in_array( $ns, [ NS_FILE, NS_TEMPLATE ] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @return InstanceEntity|null
	 */
	private function getSharedInstance(): ?InstanceEntity {
		if ( FARMER_CALLED_INSTANCE === $this->farmConfig->get( 'sharedResourcesWikiPath' ) ) {
			return null;
		}
		if ( $this->farmConfig->get( 'useSharedResources' ) === false ) {
			return null;
		}

		return $this->instanceStore->getInstanceByPath(
			$this->farmConfig->get( 'sharedResourcesWikiPath' )
		);
	}

	/**
	 * @param Title|null $title
	 * @param InstanceEntity $sharedInstance
	 * @return bool
	 */
	private function existsOnShared( ?Title $title, InstanceEntity $sharedInstance ): bool {
		if ( !$title ) {
			return false;
		}
		$pageInfo = $this->globalDatabaseQueryExecution->getForeignPage( $sharedInstance, $title );
		return (bool)$pageInfo;
	}

	/**
	 * @param User $user
	 * @param InstanceEntity $sharedInstance
	 * @return bool
	 */
	private function isEligibleForPromotion( User $user, InstanceEntity $sharedInstance ): bool {
		$targets = $this->mainConfig->get( 'ContentTransferTargets' ) ?? [];
		if ( !isset( $targets[$this->farmConfig->get( 'sharedResourcesWikiPath' )] ) ) {
			// No CT target configured
			return false;
		}

		return $this->accessStore->userHasRoleOnInstance( $user, 'editor', $sharedInstance );
	}

	/**
	 * @param Title $title
	 * @return MutableRevisionRecord|null
	 */
	private function getSharedRevision( Title $title ) {
		$sharedInstance = $this->instanceStore->getInstanceByPath(
			$this->farmConfig->get( 'sharedResourcesWikiPath' )
		);
		if ( !$sharedInstance ) {
			return null;
		}

		$foreign = $this->globalDatabaseQueryExecution->getForeignPage( $sharedInstance, $title );
		if ( $foreign === null ) {
			return null;
		}
		$rev = new MutableRevisionRecord( $title );
		$rev->setContent( SlotRecord::MAIN, new WikitextContent( $foreign['content'] ) );

		return $rev;
	}

	/**
	 * @param WebRequest $request
	 * @param InstanceEntity $sharedInstance
	 * @return array
	 */
	private function getForeignTemplateData( WebRequest $request, InstanceEntity $sharedInstance ): array {
		$params = $request->getQueryValues();
		unset( $params['sfr'] );
		$foreign = $this->foreignRequestExecution->request(
			$sharedInstance,
			'GET',
			$params
		);
		if ( !$foreign->isOK() ) {
			return [];
		}
		$decoded = json_decode( $foreign->getValue(), true );
		$pages = $decoded['pages'] ?? [];
		$formatted = [];

		foreach ( $pages as $id => $page ) {
			$formatted[$page['title']] = $page + [
				'id' => $id,
				'_is_foreign' => true,
				'_instance' => $sharedInstance->getPath(),
			];
		}

		return $formatted;
	}

}
