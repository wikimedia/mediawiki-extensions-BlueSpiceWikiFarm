<?php

namespace BlueSpice\WikiFarm\Hook\BeforeInitialize;

use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\MaintenanceScreens\NotFoundScreen;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Interwiki\InterwikiLookup;
use MediaWiki\Permissions\PermissionManager;

/**
 * Replaces the generic MediaWiki "Permission error" (Special:Badtitle) page with a
 * branded "not found" screen when the user has no read access to this wiki instance.
 * Deliberately does not reveal that the wiki exists
 */
class ShowNotFoundScreen implements BeforeInitializeHook {

	/**
	 * @var InstanceStore
	 */
	private $instanceStore;

	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @var InterwikiLookup
	 */
	private $interwikiLookup;

	/**
	 * @param InstanceStore $instanceStore
	 * @param PermissionManager $permissionManager
	 * @param InterwikiLookup $interwikiLookup
	 */
	public function __construct(
		InstanceStore $instanceStore, PermissionManager $permissionManager,
		InterwikiLookup $interwikiLookup
	) {
		$this->instanceStore = $instanceStore;
		$this->permissionManager = $permissionManager;
		$this->interwikiLookup = $interwikiLookup;
	}

	/**
	 * @inheritDoc
	 */
	public function onBeforeInitialize(
		$title,
		$unused,
		$output,
		$user,
		$request,
		$mediaWikiEntryPoint
	) {
		if ( $user->isAllowed( 'read' ) ) {
			return;
		}
		if ( $title && $this->permissionManager->userCan( 'read', $user, $title ) ) {
			return;
		}
		$instance = $this->instanceStore->getCurrentInstance();
		if ( !$instance ) {
			return;
		}

		$screen = new NotFoundScreen( $instance, $user, $this->interwikiLookup, $title );
		$response = $request->response();
		$response->statusHeader( 404 );
		$response->header( 'Content-Type: text/html; charset=utf-8' );
		echo $screen->getHtml();
		exit;
	}
}
