<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

class GenerateSitemapData extends Maintenance {

	/**
	 *
	 * @var string
	 */
	private $sitemapDataFile = '';

	/**
	 *
	 * @var array
	 */
	private $sitemapData = [];

	/**
	 *
	 * @var int[]
	 */
	private $namespaceBlacklist = [];

	/**
	 *
	 * @var array
	 */
	private $noIndexMagicWordBlacklist = [];

	/**
	 *
	 * @var array
	 */
	private $flaggedPages = [];

	/**
	 *
	 * @var Title[]
	 */
	private $pageTitles = [];

	/**
	 *
	 * @var array
	 */
	private $namespacePriorities = [];

	/**
	 * Public constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'sitemapDataFile', 'File to store the data', true );
	}

	/**
	 * Called by framework
	 * @return void
	 */
	public function execute() {
		$this->sitemapDataFile = $this->getOption( 'sitemapDataFile', '' );

		$this->readInSitemapData();
		$this->initBlacklists();
		$this->initPriorityMap();
		$this->loadAllPages();
		$this->buildData();
		$this->persistSitemapData();
	}

	/**
	 * @return void
	 */
	private function buildData() {
		$userFactory = MediaWikiServices::getInstance()->getUserFactory();
		$permissionManager = MediaWikiServices::getInstance()->getPermissionManager();
		$anonUser = $userFactory->newAnonymous();
		foreach ( $this->pageTitles as $title ) {
			if ( !$permissionManager->userCan( 'read', $anonUser, $title ) ) {
				continue;
			}
			$settings = [
				'changeFrequency' => 'weekly',
				'lastModified' => $this->makeLastModified( $title ),
				'priority' => $this->makePriority( $title )
			];

			$this->sitemapData[$title->getLocalURL()] = $settings;
		}
	}

	/**
	 *
	 * @param Title $title
	 * @return string
	 */
	private function makeLastModified( $title ) {
		$lastModified = $title->getTouched();
		if ( isset( $this->flaggedPages[$title->getArticleID()] ) ) {
			$stableRevision = $this->flaggedPages[$title->getArticleID()];
			if ( $stableRevision instanceof RevisionRecord ) {
				$lastModified = $stableRevision->getTimestamp();
			}
		}

		return $lastModified;
	}

	/**
	 *
	 * @param Title $title
	 * @return string
	 */
	private function makePriority( $title ) {
		$priority = '0.5';
		$namespaceText = $title->getNsText();
		if ( isset( $this->namespacePriorities[$namespaceText] ) ) {
			$priority = $this->namespacePriorities[$namespaceText];
		}
		return $priority;
	}

	/**
	 * @return void
	 * @throws BsInvalidNamespaceException
	 * @throws InvalidArgumentException
	 */
	private function initBlacklists() {
		// Namespace blacklist
		$namespaceExcludesMessage = Message::newFromKey( 'farm-sitemap-namespace-excludes' );
		if ( $namespaceExcludesMessage->exists() ) {
			$namespaceExcludesText = $namespaceExcludesMessage->inContentLanguage()->plain();
			$excludedNamespaces = explode( ',', $namespaceExcludesText );
			$this->namespaceBlacklist
				= BsNamespaceHelper::getNamespaceIdsFromAmbiguousArray( $excludedNamespaces );
		}

		// __NOINDEX__ blacklist
		$dbr = $this->getDB( DB_REPLICA );
		$res = $dbr->select( 'page_props', 'pp_page', [
			'pp_propname' => 'noindex'
		], __METHOD__ );
		foreach ( $res as $row ) {
			$this->noIndexMagicWordBlacklist[] = $row->pp_page;
		}
	}

	/**
	 * @return void
	 */
	private function initPriorityMap() {
		$namespacePrioMapMessage = Message::newFromKey( 'farm-sitemap-namespace-priorities' );
		if ( $namespacePrioMapMessage->exists() ) {
			$namespacePrioMapJSON = $namespacePrioMapMessage->inContentLanguage()->plain();
			$this->namespacePriorities = FormatJson::decode( $namespacePrioMapJSON );
		}
	}

	/**
	 * @return void
	 */
	private function loadAllPages() {
		$conds = [];
		$namespaceWhitelist = $this->makeNamespaceWhitelist();
		if ( !empty( $namespaceWhitelist ) ) {
			$conds = [ 'page_namespace' => $namespaceWhitelist ];
		}

		$dbr = $this->getDB( DB_REPLICA );
		$res = $dbr->select( 'page', '*', $conds, __METHOD__ );
		foreach ( $res as $row ) {
			$title = Title::newFromRow( $row );
			if ( in_array( $title->getArticleID(), $this->noIndexMagicWordBlacklist ) ) {
				continue;
			}
			if ( $this->underApprovalButNoApprovedVersion( $title ) ) {
				continue;
			}

			$this->pageTitles[] = $title;
		}
	}

	/**
	 *
	 * @param title $title
	 * @return bool
	 */
	private function underApprovalButNoApprovedVersion( $title ) {
		$services = MediaWikiServices::getInstance();
		if ( !$services->hasService( 'ContentStabilization.Lookup' ) ) {
			return false;
		}

		/** @var \MediaWiki\Extension\ContentStabilization\StabilizationLookup $lookup */
		$lookup = $services->getService( 'ContentStabilization.Lookup' );
		if ( !$lookup->isStabilizationEnabled( $title ) ) {
			return false;
		}

		if ( !$lookup->hasStable( $title ) ) {
			return true;
		}

		$stableRevision = $lookup->getLastStableRevision( $title );
		if ( !$stableRevision ) {
			return false;
		}
		$this->flaggedPages[$title->getArticleID()] = $stableRevision;

		return false;
	}

	/**
	 *
	 * @return int[]
	 */
	private function makeNamespaceWhitelist() {
		$namespaceWhitelist = [];
		$lang = RequestContext::getMain()->getLanguage();
		$allNamespaces = $lang->getNamespaceIds();
		$namespaceInfo = MediaWikiServices::getInstance()->getNamespaceInfo();
		foreach ( $allNamespaces as $namespaceId ) {
			if ( !$namespaceInfo->isContent( $namespaceId ) ) {
				continue;
			}
			if ( $namespaceInfo->isTalk( $namespaceId ) ) {
				// TODO: Is this good?
				continue;
			}
			if ( in_array( $namespaceId, $this->namespaceBlacklist ) ) {
				continue;
			}

			$namespaceWhitelist[] = $namespaceId;
		}

		return $namespaceWhitelist;
	}

	/**
	 * @return void
	 */
	private function readInSitemapData() {
		if ( !file_exists( $this->sitemapDataFile ) ) {
			$this->persistSitemapData();
			return;
		}
		$this->sitemapData = FormatJson::decode(
			file_get_contents( $this->sitemapDataFile ),
			true
		);
	}

	/**
	 * @return void
	 */
	private function persistSitemapData() {
		file_put_contents(
			$this->sitemapDataFile,
			FormatJson::encode(
				$this->sitemapData,
				true
			)
		);
	}
}

$maintClass = 'GenerateSitemapData';
require_once RUN_MAINTENANCE_IF_MAIN;
