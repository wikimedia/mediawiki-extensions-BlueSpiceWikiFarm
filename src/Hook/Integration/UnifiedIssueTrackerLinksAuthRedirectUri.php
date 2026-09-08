<?php

namespace BlueSpice\WikiFarm\Hook\Integration;

use MediaWiki\Config\Config;
use MediaWiki\Extension\IssueTrackerLinks\DataProviderBackend\IDataProviderBackend;
use MediaWiki\Extension\IssueTrackerLinks\Hook\IssueTrackerLinksAuthHook;
use MediaWiki\Extension\IssueTrackerLinks\Hook\IssueTrackerLinksAuthRedirectUriHook;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class UnifiedIssueTrackerLinksAuthRedirectUri implements IssueTrackerLinksAuthHook, IssueTrackerLinksAuthRedirectUriHook {

	/**
	 * @param Config $farmConfig
	 */
	public function __construct(
		private readonly Config $farmConfig
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function onIssueTrackerLinksAuth( SpecialPage $authPage ): void {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) === false ) {
			return;
		}

		if ( FARMER_CALLED_INSTANCE !== 'w' ) {
			// Perform Oauth flow from the main wiki
			$url = Title::newFromText( 'w:Special:IssueAuth/start' )->getFullURL( [
				'provider' => $authPage->getRequest()->getText( 'provider' )
			] );
			header( 'Location: ' . $url );
			exit;
		}
	}

	/**
	 * @param IDataProviderBackend $provider
	 * @param string &$redirectUri
	 * @return void
	 */
	public function onIssueTrackerLinksAuthRedirectUri( IDataProviderBackend $provider, string &$redirectUri ): void {
		if ( $this->farmConfig->get( 'useGlobalAccessControl' ) === false ) {
			return;
		}
		$base = $this->farmConfig->get( 'basePath' );
		$base = trim( $base, '/' );
		if ( empty( $base ) ) {
			$base = '/';
		} else {
			$base = "/$base/";
		}
		$server = trim( $this->farmConfig->get( 'globalServer' ), '/' );
		$redirectUri = "{$server}{$base}wiki/Special:IssueAuth/callback";
	}
}
