<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\Discovery\Hook\BlueSpiceDiscoveryTemplateDataProviderAfterInit;
use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Hook\BeforeParserFetchTemplateRevisionRecordHook;
use MediaWiki\Hook\SkinTemplateNavigation__UniversalHook;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Output\Hook\BeforePageDisplayHook;
use MediaWiki\Revision\MutableRevisionRecord;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\User;
use OOUI\MessageWidget;

class HandleSharedResources implements
	BeforeParserFetchTemplateRevisionRecordHook,
	SkinTemplateNavigation__UniversalHook,
	BlueSpiceDiscoveryTemplateDataProviderAfterInit,
	BeforePageDisplayHook
{

	/**
	 * @param Config $farmConfig
	 * @param Config $mainConfig
	 * @param TitleFactory $titleFactory
	 * @param GlobalDatabaseQueryExecution $globalDatabaseQueryExecution
	 * @param InstanceStore $instanceStore
	 * @param IAccessStore $accessStore
	 */
	public function __construct(
		private readonly Config $farmConfig,
		private readonly Config $mainConfig,
		private readonly TitleFactory $titleFactory,
		private readonly GlobalDatabaseQueryExecution $globalDatabaseQueryExecution,
		private readonly InstanceStore $instanceStore,
		private readonly IAccessStore $accessStore
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

}
