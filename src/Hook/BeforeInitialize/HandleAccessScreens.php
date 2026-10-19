<?php

namespace BlueSpice\WikiFarm\Hook\BeforeInitialize;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\MaintenanceScreens\MaintenanceScreen;
use BlueSpice\WikiFarm\MaintenanceScreens\NonExistingInstanceScreen;
use BlueSpice\WikiFarm\MaintenanceScreens\NotFoundScreen;
use BlueSpice\WikiFarm\MaintenanceScreens\SuspendedScreen;
use BlueSpice\WikiFarm\NonExistingInstanceEntity;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\Config;
use MediaWiki\Hook\BeforeInitializeHook;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\WebRequest;
use SpecialPage;

/**
 * Replaces the generic MediaWiki "Permission error" (Special:Badtitle) page with a
 * branded "not found" screen when the user has no read access to this wiki instance.
 * Deliberately does not reveal that the wiki exists
 */
class HandleAccessScreens implements BeforeInitializeHook {
	/**
	 * @var InstanceStore
	 */
	private $instanceStore;
	/**
	 * @var PermissionManager
	 */
	private $permissionManager;

	/**
	 * @param InstanceStore $instanceStore
	 * @param PermissionManager $permissionManager
	 */
	public function __construct(
		InstanceStore $instanceStore, PermissionManager $permissionManager
	) {
		$this->instanceStore = $instanceStore;
		$this->permissionManager = $permissionManager;
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
		if ( $title && $title->isSpecial( 'Userlogin' ) ) {
			return;
		}
		$instance = $this->instanceStore->getCurrentInstance();
		if ( !$instance && defined( 'FARMER_CALLED_INSTANCE_OBJECT' ) ) {
			$instance = FARMER_CALLED_INSTANCE_OBJECT;
		}
		if ( $instance instanceof NonExistingInstanceEntity ) {
			if ( !$user->isRegistered() ) {
				$this->redirectToLogin( $title, $output, $request );
			}
			$screen = $this->permissionManager->userHasRight( $user, 'wikifarm-createwiki' ) ?
				new NonExistingInstanceScreen( $instance ) : new NotFoundScreen( $instance );
			$this->showScreen( $screen, $request );
		}
		if ( $instance && $instance->getStatus() === InstanceEntity::STATUS_SUSPENDED ) {
			$screen = $this->permissionManager->userHasRight( $user, 'read' ) ?
				new SuspendedScreen( $instance ) : new NotFoundScreen( $instance );
			$this->showScreen( $screen, $request );
		}
		if ( !$title && $user->isAllowed( 'read' ) ) {
			return;
		}
		if ( $title && $this->permissionManager->userCan( 'read', $user, $title ) ) {
			return;
		}
		if ( !$user->isRegistered() ) {
			$this->redirectToLogin( $title, $output, $request );
		}
		if ( !$instance ) {
			return;
		}
		$this->showScreen( new NotFoundScreen( $instance ), $request );
	}

	/**
	 * @param mixed $title
	 * @param mixed $output
	 * @param WebRequest $request
	 */
	private function redirectToLogin( $title, $output, WebRequest $request ): void {
		$query = $request->getQueryValues();
		unset( $query['title'], $query['returnto'], $query['returntoquery'] );
		$output->redirect( $this->getLoginUrl( $title, $query ) );
		$output->output();
		exit;
	}

	/**
	 * @param mixed $title
	 * @param array $query
	 * @return string
	 */
	protected function getLoginUrl( $title, array $query ): string {
		$config = $GLOBALS['wgWikiFarmConfigInternal'] ?? null;
		if ( !( $config instanceof Config ) ) {
			return SpecialPage::getSafeTitleFor( 'UserLogin' )->getFullURL();
		}
		$rootUrl = ( new RootInstanceEntity() )->getUrl( $config );
		return $rootUrl . '/index.php?' . wfArrayToCgi( [
			'title' => 'Special:UserLogin',
			'returnto' => $title ? $title->getPrefixedText() : '',
			'returntoquery' => wfArrayToCgi( $query ),
		] );
	}

	/**
	 * @param MaintenanceScreen $screen
	 * @param WebRequest $request
	 */
	private function showScreen( MaintenanceScreen $screen, WebRequest $request ): void {
		$response = $request->response();
		if ( $screen instanceof NotFoundScreen ) {
			$response->statusHeader( 404 );
		}
		$response->header( 'Content-Type: text/html; charset=utf-8' );
		echo $screen->getHtml();
		exit;
	}
}
