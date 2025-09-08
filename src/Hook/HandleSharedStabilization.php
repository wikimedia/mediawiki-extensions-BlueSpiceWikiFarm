<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Setup;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationFetchForeignRevisionForTransclusionHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationGetCurrentInclusionsHook;
use MediaWiki\Extension\ContentStabilization\Hook\Interfaces\ContentStabilizationGetStableForeignInclusionHook;
use MediaWiki\Page\PageIdentity;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\User\UserIdentity;

class HandleSharedStabilization extends HandleSharedResources implements
	ContentStabilizationGetCurrentInclusionsHook,
	ContentStabilizationFetchForeignRevisionForTransclusionHook,
	ContentStabilizationGetStableForeignInclusionHook
{

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationGetCurrentInclusions( PageIdentity $page, array &$res ): void {
		// If page contains transclusions that do not exist locally, but exist in shared resources wiki,
		// set it as the source of the transclusion and fill in details
		if (
			!$this->farmConfig->get( 'useSharedResources' ) ||
			FARMER_CALLED_INSTANCE === $this->farmConfig->get( 'sharedResourcesWikiPath' )
		) {
			return;
		}
		$transclusions = $res['transclusions'] ?? [];
		if ( !$this->sharedInstance ) {
			// No shared instance configured, bail out
			return;
		}
		$sharedInstanceWikiId = Setup::getWikiId(
			$this->sharedInstance->getDbName(), $this->sharedInstance->getDbPrefix()
		);
		foreach ( $transclusions as &$transclusion ) {
			$title = $this->titleFactory->makeTitle( $transclusion['namespace'], $transclusion['title'] );
			if ( $title->exists() ) {
				// Title exists locally, bail out
				continue;
			}
			$sharedRevision = $this->getSharedRevision( $title, $this->sharedInstance );
			if ( $sharedRevision ) {
				$transclusion['revision'] = $sharedRevision->getId();
				$transclusion['source'] = $sharedInstanceWikiId;
			}
		}
		$res['transclusions'] = $transclusions;
	}

	/**
	 * @param array &$inclusion
	 * @param string $type
	 * @param int $revLimit
	 * @inheritDoc
	 */
	public function onContentStabilizationGetStableForeignInclusion(
		array &$inclusion, string $type, int $revLimit
	): void {
		if ( $type === 'images' && $inclusion['source'] !== 'farmsharedresources' ) {
			return;
		}
		if ( $type === 'transclusions' ) {
			if ( $inclusion['namespace'] === NS_MAIN ) {
				$titleText = $inclusion['title'];
			} else {
				$canonicalNamespace = $this->namespaceInfo->getCanonicalName( $inclusion['namespace'] );
				if ( !$canonicalNamespace ) {
					return;
				}
				$titleText = $canonicalNamespace . ':' . $inclusion['title'];
			}
		} elseif ( $type === 'images' ) {
			$titleText = $this->namespaceInfo->getCanonicalName( NS_FILE ) . ':' . $inclusion['name'];
		} else {
			return;
		}

		$params = [
			'action' => 'query',
			'prop' => 'revisions',
			'titles' => $titleText,
			'rvslots' => SlotRecord::MAIN,
			'rvprop' => 'ids|stabilization',
			'rvlimit' => 100,
			'format' => 'json'
		];
		$response = $this->globalDatabaseQueryExecution->getActionApiResults( $this->sharedInstance, $params );
		$pages = $response['query']['pages'] ?? [];
		$page = reset( $pages );

		if ( !isset( $page['stabilization_enabled'] ) ) {
			return;
		}
		$usedRev = null;
		foreach ( $page['revisions'] as $revision ) {
			if ( $revision['stabilization'] ) {
				if ( $revLimit > 0 && $revision['revid'] > $revLimit ) {
					// Skip revisions that are newer than the limit
					continue;
				}
				if ( $usedRev && $revision['revid'] < $usedRev ) {
					continue;
				}
				$inclusion['revision'] = $revision['revid'];
				$usedRev = $revision['revid'];
				if ( $type === 'images' ) {
					$inclusion['timestamp'] = '';
					$inclusion['sha1'] = '';
					if ( isset( $revision['stabilization']['file'] ) ) {
						$inclusion['timestamp'] = $revision['stabilization']['file']['timestamp'] ?? null;
						$inclusion['sha1'] = $revision['stabilization']['file']['sha1'] ?? null;
					}
				}

			}
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onContentStabilizationFetchForeignRevisionForTransclusion(
		array $transclusion, ?RevisionRecord &$revision, ?UserIdentity $forUser, array $options = []
	): void {
		// This happens when transclusion is stabilized, when selecting which revision of the
		// transclusion to use. If transclusion comes from the shared resources wiki, get proper
		// revision from there
		if ( !$this->sharedInstance ) {
			// No shared instance configured, bail out
			return;
		}
		$sharedInstanceWikiId = Setup::getWikiId(
			$this->sharedInstance->getDbName(), $this->sharedInstance->getDbPrefix()
		);
		if ( $transclusion['source'] === $sharedInstanceWikiId ) {
			$title = $this->titleFactory->makeTitle( $transclusion['namespace'], $transclusion['title'] );
			$revision = $this->getSharedRevision( $title, $this->sharedInstance, $transclusion['revision'] );
		}
	}
}
