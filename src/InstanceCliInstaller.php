<?php

namespace BlueSpice\WikiFarm;

require_once $GLOBALS['IP'] . '/extensions/BlueSpiceFoundation/src/Installer/AutoExtensionHandler.php';

use Exception;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Installer\CliInstaller;
use MediaWiki\Installer\DatabaseInstaller;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;

$GLOBALS['wgMessagesDirs']['BlueSpiceFarmInstaller'] = __DIR__ . '/../i18n';

class InstanceCliInstaller extends CliInstaller {

	/**
	 * Holds extensions processed in method getAutoExtension.
	 * This extensions are skipped in getAutoExtensionLegacyHooks.
	 *
	 * @var array
	 */
	private $processedAutoExtensions = [];

	/**
	 * Basically a copy of `CliInstaller::execute` but without the check for "$IP/LocalSettings.php"
	 * @return Status
	 */
	public function execute() {
		// If APC is available, use that as the MainCacheType, instead of nothing.
		// This is hacky and should be consolidated with WebInstallerOptions.
		// This is here instead of in __construct(), because it should run run after
		// doEnvironmentChecks(), which populates '_Caches'.
		if ( count( $this->getVar( '_Caches' ) ) ) {
			// We detected a CACHE_ACCEL implementation, use it.
			$this->setVar( '_MainCacheType', 'accel' );
		}

		// Disable upgrade-check
		/*
		$vars = Installer::getExistingLocalSettings();
		if ( $vars ) {
			$status = Status::newFatal( "config-localsettings-cli-upgrade" );
			$this->showStatusMessage( $status );
			return $status;
		}
		*/
		// // Disable upgrade-check - END

		$result = $this->performInstallation(
			[ $this, 'startStage' ],
			[ $this, 'endStage' ]
		);
		// PerformInstallation bails on a fatal, so make sure the last item
		// completed before giving 'next.' Likewise, only provide back on failure
		$lastStepStatus = end( $result );
		if ( $lastStepStatus->isOK() ) {
			return Status::newGood();
		} else {
			return $lastStepStatus;
		}
	}

	/**
	 * Get an MW configuration variable, or internal installer configuration variable.
	 * The defaults come from $GLOBALS (ultimately DefaultSettings.php).
	 * Installer variables are typically prefixed by an underscore.
	 *
	 * @param string $name
	 * @param mixed|null $default
	 *
	 * @return mixed
	 */
	public function getVar( $name, $default = null ) {
		if ( strpos( $name, 'ext-' ) === 0 ) {
			$this->setVar( $name, '0' );
		}

		// Extension schemas will be added by extra `update.php` step
		// Extensions tend to break the installer (e.g. Extension:OATHAuth, see T371849)
		if ( $name === '_Extensions' ) {
			return [];
		}

		return parent::getVar( $name, $default );
	}

	/**
	 * @inheritDoc
	 */
	protected function includeExtensions() {
		// Don't load extensions
		return Status::newGood();
	}

	/**
	 *
	 * @param string $path
	 */
	public function writeConfigurationFile( $path ) {
		// NOOP
	}

	/** @inheritDoc */
	public function showMessage( $msg, ...$params ) {
		wfDebugLog( 'BlueSpiceWikiFarm', $msg );
		wfDebugLog( 'BlueSpiceWikiFarm', var_export( $params, true ) );
	}

	/** @inheritDoc */
	public function showError( $msg, ...$params ) {
		wfDebugLog( 'BlueSpiceWikiFarm', $msg );
		wfDebugLog( 'BlueSpiceWikiFarm', var_export( $params, true ) );
	}

	/**
	 *
	 * @param Status $status
	 */
	public function showStatusMessage( Status $status ) {
		if ( !$status->isGood() ) {
			wfDebugLog( 'BlueSpiceWikiFarm', $status->getMessage()->inLanguage( 'en' )->text() );
		}
	}

	/** @inheritDoc */
	protected function createMainpage( DatabaseInstaller $installer ) {
		$status = Status::newGood();
		$title = MediaWikiServices::getInstance()->getTitleFactory()->newMainPage();
		if ( $title->exists() ) {
			$status->warning( 'config-install-mainpage-exists' );
			return $status;
		}
		try {
			$path = dirname( __DIR__ ) . '/content/mainpage/farm.html';
			echo "$path\n";

			$rawContent = file_get_contents( $path );
			$processedContent = preg_replace_callback(
				'#\{\{int:(.*?)\}\}#si',
				static function ( $matches ) {
					return wfMessage( $matches[1] )->inContentLanguage()->text();
				},
				$rawContent
			);
			$content = new WikitextContent( $processedContent );
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()
				->newFromTitle( $title );
			$user = User::newSystemUser( 'BlueSpice default' );

			$updater = $page->newPageUpdater( $user );
			$updater->setContent( SlotRecord::MAIN, $content );
			$comment = CommentStoreComment::newUnsavedComment( '' );
			$updater->saveRevision( $comment, EDIT_NEW );
			$status = $updater->getStatus();
		} catch ( Exception $e ) {
			// using raw, because $wgShowExceptionDetails can not be set yet
			$status->fatal( 'config-install-mainpage-failed', $e->getMessage() );
		}

		$this->createSidebar( $installer );

		return $status;
	}

	/**
	 * Sidebar with BlueSpice content
	 *
	 * @param DatabaseInstaller $installer
	 * @return Status
	 */
	protected function createSidebar( DatabaseInstaller $installer ) {
		$status = Status::newGood();
		$title = Title::makeTitleSafe( NS_MEDIAWIKI, 'Sidebar' );
		if ( $title->exists() ) {
			$status->warning( 'config-install-mainpage-exists' );
			return $status;
		}
		try {
			$path = dirname( __DIR__ ) . '/content/sidebar/farm.wikitext';
			echo "$path\n";

			$rawContent = file_get_contents( $path );
			$content = new WikitextContent( $rawContent );
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()
				->newFromTitle( $title );
			$user = User::newSystemUser( 'BlueSpice default' );

			$updater = $page->newPageUpdater( $user );
			$updater->setContent( SlotRecord::MAIN, $content );
			$comment = CommentStoreComment::newUnsavedComment( '' );
			$updater->saveRevision( $comment, EDIT_NEW );
			$status = $updater->getStatus();
		} catch ( Exception $e ) {
			// using raw, because $wgShowExceptionDetails can not be set yet
			$status->fatal( 'config-install-sidebar-failed', $e->getMessage() );
		}

		return $status;
	}
}
