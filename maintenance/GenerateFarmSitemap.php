<?php

require_once dirname( __DIR__, 3 ) . '/maintenance/Maintenance.php';

use BlueSpice\WikiFarm\InstanceManager;
use Icamys\SitemapGenerator\SitemapGenerator;
use MediaWiki\Json\FormatJson;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;

class GenerateFarmSitemap extends Maintenance {

	/**
	 *
	 * @var string
	 */
	private $basepath = '';

	/**
	 *
	 * @var string
	 */
	private $baseurl = '';

	/**
	 *
	 * @var string
	 */
	private $sitemapsDir = '';

	/**
	 *
	 * @var string
	 */
	private $sitemapDataFile = '';

	/**
	 * Public constructor
	 */
	public function __construct() {
		parent::__construct();
		$this->addOption( 'basepath', 'The webroot directory', true );
		$this->addOption( 'baseurl', 'The web accessible URL', true );
	}

	/**
	 * Called by framework
	 * @return void
	 */
	public function execute() {
		$this->basepath = $this->getOption( 'basepath', '' );
		$this->baseurl = $this->getOption( 'baseurl', '' );

		$this->ensureSitemapsDir();
		$this->createInstanceSitemapData();
		$this->createFarmSitemap();
	}

	private function createInstanceSitemapData() {
		$farmConfig = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );
		$configDir = $farmConfig->get( 'instanceDirectory' );
		$this->sitemapDataFile = $this->sitemapsDir . '/allinstancesdata.json';

		$directory = new DirectoryIterator( $configDir );
		foreach ( $directory as $fileInfo ) {
			if ( $fileInfo->isFile() || $fileInfo->isDot() ) {
				continue;
			}

			$sfr = $fileInfo->getBasename();
			if ( $this->hasNoEnableSitemapMeta( $sfr ) ) {
				$this->output( "Skipping instance '$sfr', due to `ENABLE-SITEMAP` keyword\n" );
				continue;
			}

			$phpCli = $GLOBALS['wgPhpCli'];
			$this->output( "Create sitemap for instance '$sfr'\n" );
			$cmd = sprintf(
				"$phpCli %s %s --sfr=%s",
				Shell::escape( __DIR__ . '/GenerateSitemapData.php' ),
				'--sitemapDataFile=' . Shell::escape( $this->sitemapDataFile ),
				Shell::escape( $sfr )
			);
			$this->output( "$cmd \n" );

			$result = [];
			$code = 0;
			exec( $cmd, $result, $code ); // phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec

			if ( $code > 0 ) {
				$this->fatalError( "An error occurred: " . implode( "\n", $result ) );
			}
		}
	}

	/**
	 *
	 * @param string $intanceDir
	 * @return bool
	 */
	private function hasNoEnableSitemapMeta( string $intanceDir ) {
		/** @var InstanceManager $instanceManager */
		$instanceManager = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$instance = $instanceManager->getStore()->getInstanceByPath( $intanceDir );
		if ( !$instance ) {
			return true;
		}
		$meta = $instance->getMetadata();
		if ( !isset( $meta['keywords'] ) || !in_array( 'ENABLE-SITEMAP', $meta['keywords'] ) ) {
			return true;
		}
		return false;
	}

	/**
	 *
	 * @var array
	 */
	private $allInstancesData = [];

	private function createFarmSitemap() {
		if ( !file_exists( $this->sitemapDataFile ) ) {
			$this->output( "No sitemap data found. Skipping creation of farm sitemap.\n" );
			return;
		}
		$this->allInstancesData = FormatJson::decode(
			file_get_contents( $this->sitemapDataFile ),
			true
		);
		unlink( $this->sitemapDataFile );

		$sitemapGenerator = new SitemapGenerator( $this->baseurl, $this->sitemapsDir . '/' );
		$sitemapGenerator->enableCompression();

		$sitemapGenerator->setMaxUrlsPerSitemap( 50000 );

		// Set the sitemap file name
		$sitemapGenerator->setSitemapFileName( 'sitemap.xml' );

		// Set the sitemap index file name
		$sitemapGenerator->setSitemapIndexFileName( 'sitemap-index.xml' );

		foreach ( $this->allInstancesData as $url => $settings ) {
			$changeFrequency = $settings['changeFrequency'];
			$lastModified = new DateTime( $settings['lastModified'] );
			$priority = $settings['priority'];

			$sitemapGenerator->addURL( $url, $lastModified, $changeFrequency, $priority );
		}

		// Flush all stored urls from memory to the disk and close all necessary tags.
		$sitemapGenerator->flush();

		// Move flushed files to their final location. Compress if the option is enabled.
		$sitemapGenerator->finalize();

		// For the future
		#$sitemapGenerator->updateRobots();
		#$sitemapGenerator->submitSitemap();
	}

	private function ensureSitemapsDir() {
		if ( empty( $this->basepath ) ) {
			$this->fatalError( "Parameter 'basepath' must not be empty!" );
		}

		$this->sitemapsDir = $this->basepath . '/sitemaps';

		if ( !file_exists( $this->sitemapsDir ) ) {
			wfMkdirParents( $this->sitemapsDir );
		}
	}

}

$maintClass = 'GenerateFarmSitemap';
require_once RUN_MAINTENANCE_IF_MAIN;
