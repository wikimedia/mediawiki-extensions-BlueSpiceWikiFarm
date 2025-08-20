<?php

use BlueSpice\WikiFarm\InstanceCliInstaller;
use MediaWiki\Installer\Installer;
use MediaWiki\Installer\InstallException;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;

$mwInstallPath = getenv( 'MW_INSTALL_PATH' ) !== false
	? getenv( 'MW_INSTALL_PATH' )
	: dirname( __DIR__, 4 );
$extInstallPath = dirname( __DIR__, 2 );

require_once $mwInstallPath . '/maintenance/Maintenance.php';

unset( $mwInstallPath );
unset( $extInstallPath );

define( 'MW_CONFIG_CALLBACK', [ Installer::class, 'overrideConfig' ] );
define( 'MEDIAWIKI_INSTALL', true );

class InstallInstance extends Maintenance {

	/**
	 *
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'instanceDisplayName', 'Display name for the new instance', true, true );
		$this->addOption( 'scriptpath', 'Script path for the new instance', true, true );
		$this->addOption( 'dbname', 'DB name the new instance', true, true );
		$this->addOption( 'dbprefix', 'DB prefix the new instance', true, true );
		$this->addOption( 'dbserver', 'The name or IP address of the database server', true, true );
		$this->addOption( 'dbuser', 'The username of accessing the database server', true, true );
		$this->addOption( 'dbpass', 'The password of accessing the database server', true, true );
		$this->addOption( 'lang', 'The language to be used', false, true );
		$this->addOption( 'server', 'Server domain to be used', false, true );
	}

	/**
	 * @return void
	 * @throws InstallException
	 * @throws MaintenanceFatalError
	 */
	public function execute() {
		$installer = new InstanceCliInstaller(
			$this->getOption( 'instanceDisplayName' ),
			'WikiSysop',
			[
				'scriptpath' => $this->getOption( 'scriptpath' ),
				'dbname' => $this->getOption( 'dbname' ),
				'dbserver' => $this->getOption( 'dbserver' ),
				'dbuser' => $this->getOption( 'dbuser' ),
				'dbpass' => $this->getOption( 'dbpass' ),
				'dbprefix' => $this->getOption( 'dbprefix' ),
				'server' => $this->getOption( 'server' ),
				'pass' => MWCryptRand::generateHex( 16 ),
				'lang' => $this->getOption( 'lang' )
			]
		);

		$status = $installer->execute();
		if ( !$status->isGood() ) {
			$this->fatalError( $status->getMessage()->inLanguage( 'en' )->text() );
		}
	}
}

$maintClass = 'InstallInstance';
require_once RUN_MAINTENANCE_IF_MAIN;
