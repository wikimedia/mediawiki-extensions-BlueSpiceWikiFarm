<?php

namespace BlueSpice\WikiFarm;

require_once $GLOBALS['IP'] . '/extensions/BlueSpiceFoundation/src/Installer/AutoExtensionHandler.php';

use Exception;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Installer\CliInstaller;
use MediaWiki\Installer\Task\ITaskContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use StatusValue;

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
	 * @inheritDoc
	 */
	public function __construct( $siteName, $admin = null, array $options = [] ) {
		parent::__construct( $siteName, $admin, $options );

		$this->setVar( 'wgInstallerInitialPages', [] );
		$this->addInstallStep( [
			'name' => 'wikifarm-default-content',
			'description' => 'config-install-mainpage',
			'callback' => [ $this, 'createMainpage' ],
		], 'mainpage' );
	}

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

		if ( $result->isOK() ) {
			return Status::newGood();
		} else {
			return Status::newFatal( $result->getValue() );
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
	 * @param StatusValue $status
	 */
	public function showStatusMessage( StatusValue $status ) {
		if ( !$status->isGood() ) {
			wfDebugLog( 'BlueSpiceWikiFarm', $status->getMessage()->inLanguage( 'en' )->text() );
		}
	}

	/**
	 * Install task: main page, "Getting started" help page (if the edition has one) and sidebar.
	 * Files used by these pages are imported by `ImportDefaultContentFiles` during update.php,
	 * because the installer does not know the upload directory of the instance.
	 *
	 * @param ITaskContext $context
	 * @return Status
	 */
	public function createMainpage( ITaskContext $context ) {
		$contentDir = dirname( __DIR__ ) . '/content';
		$edition = $this->getEdition();

		$mainpagePath = "$contentDir/mainpage/$edition.html";
		if ( $edition === '' || !file_exists( $mainpagePath ) ) {
			$mainpagePath = "$contentDir/mainpage/farm.html";
		}
		$status = $this->createPage(
			MediaWikiServices::getInstance()->getTitleFactory()->newMainPage(),
			$this->getLocalizedContent( $mainpagePath ),
			'config-install-mainpage-failed'
		);

		$helpPath = "$contentDir/gettingstarted/$edition.html";
		if ( $edition !== '' && file_exists( $helpPath ) ) {
			$helpTitle = wfMessage( "wikifarm-gettingstarted-content-$edition-title" )
				->inContentLanguage()->plain();
			$status->merge( $this->createPage(
				Title::makeTitleSafe( NS_HELP, $helpTitle ),
				$this->getLocalizedContent( $helpPath ),
				'config-install-mainpage-failed'
			) );
		}
		$status->merge( $this->createSidebar() );

		return $status;
	}

	/**
	 * BlueSpice edition from `$IP/BLUESPICE-EDITION`, same source as the
	 * `BsEdition` class of bluespice/mw-config-overrides
	 *
	 * @return string Empty if unknown
	 */
	private function getEdition(): string {
		$path = $GLOBALS['IP'] . '/BLUESPICE-EDITION';
		if ( !file_exists( $path ) ) {
			return '';
		}
		$lines = explode( "\n", file_get_contents( $path ) );
		$edition = strtolower( trim( $lines[0] ) );

		return preg_match( '/^[a-z]+$/', $edition ) ? $edition : '';
	}

	/**
	 * Sidebar with BlueSpice content
	 *
	 * @return Status
	 */
	protected function createSidebar() {
		return $this->createPage(
			Title::makeTitleSafe( NS_MEDIAWIKI, 'Sidebar' ),
			file_get_contents( dirname( __DIR__ ) . '/content/sidebar/farm.wikitext' ),
			'config-install-sidebar-failed'
		);
	}

	/**
	 * Replaces `{{int:...}}` with the raw wikitext of the message in the content language,
	 * so templates and magic words inside the messages are kept for the page to render,
	 * and `{{InstallerOption:...}}` like core's InitialContentTask does.
	 *
	 * @param string $path
	 * @return string
	 */
	private function getLocalizedContent( string $path ): string {
		$content = preg_replace_callback(
			'#\{\{int:(.*?)\}\}#si',
			static function ( $matches ) {
				return wfMessage( $matches[1] )->inContentLanguage()->plain();
			},
			file_get_contents( $path )
		);
		return preg_replace_callback(
			'/\{\{ *InstallerOption: *(\w+) *}}/',
			function ( $matches ) {
				return (string)$this->getVar( '_' . $matches[1] );
			},
			$content
		);
	}

	/**
	 * @param Title $title
	 * @param string $text
	 * @param string $failureMessage
	 * @return Status
	 */
	private function createPage( Title $title, string $text, string $failureMessage ) {
		$status = Status::newGood();
		if ( $title->exists() ) {
			$status->warning( 'config-install-mainpage-exists' );
			return $status;
		}
		try {
			$page = MediaWikiServices::getInstance()->getWikiPageFactory()
				->newFromTitle( $title );
			$user = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );

			$updater = $page->newPageUpdater( $user );
			$updater->setContent( SlotRecord::MAIN, new WikitextContent( $text ) );
			$comment = CommentStoreComment::newUnsavedComment( '' );
			$updater->saveRevision( $comment, EDIT_NEW );
			$status = $updater->getStatus();
		} catch ( Exception $e ) {
			// using raw, because $wgShowExceptionDetails can not be set yet
			$status->fatal( $failureMessage, $e->getMessage() );
		}

		return $status;
	}
}
