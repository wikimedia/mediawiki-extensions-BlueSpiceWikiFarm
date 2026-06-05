<?php

use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstanceTemplateProvider;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;

require_once dirname( __FILE__, 4 ) . '/maintenance/Maintenance.php';

/**
 * Creates a new wiki instance via the ProcessManager, running the same steps as the REST API.
 *
 * Usage:
 *   php CreateInstance.php --path=mywiki
 *   php CreateInstance.php --path=mywiki --display-name="My Wiki" --lang=de --user=Admin
 *   php CreateInstance.php --path=mywiki --template=default --metadata='{"group":"dev"}' --config='{"wgSitename":"My Wiki"}'
 */
class CreateInstance extends Maintenance {

	/** @var InstanceManager */
	private InstanceManager $manager;

	/** @var ProcessManager */
	private ProcessManager $processManager;

	public function __construct() {
		parent::__construct();
		$this->addDescription( 'Creates a new wiki farm instance.' );

		$this->addOption( 'path', 'URL path / identifier for the new instance (e.g. "mywiki")', true, true );
		$this->addOption( 'display-name', 'Human-readable display name (defaults to --path)', false, true );
		$this->addOption( 'lang', 'Language code for the new wiki (defaults to the farm default)', false, true );
		$this->addOption( 'template', 'Template name to import after installation', false, true );
		$this->addOption(
			'user',
			'Username to copy into the new instance as administrator (defaults to "WikiSysop")',
			false,
			true
		);
		$this->addOption(
			'metadata',
			'JSON object of metadata key/value pairs (e.g. \'{"group":"sales","keywords":["a","b"]}\')',
			false,
			true
		);
		$this->addOption(
			'config',
			'JSON object of MediaWiki config overrides to store for the instance (e.g. \'{"wgSitename":"My Wiki"}\')',
			false,
			true
		);
	}

	/**
	 * @return void
	 * @throws MaintenanceFatalError
	 */
	public function execute() {
		$services = $this->getServiceContainer();
		$this->manager = $services->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		$this->processManager = $services->getService( 'ProcessManager' );

		$path = $this->getOption( 'path' );
		$displayName = $this->getOption( 'display-name', $path );
		$userName = $this->getOption( 'user', 'WikiSysop' );

		$metadata = $this->parseJsonOption( 'metadata', [] );
		$config = $this->parseJsonOption( 'config', [] );
		$template = $this->resolveTemplate( $this->getOption( 'template', '' ) );

		$options = [
			'userName' => $userName,
			'metadata' => $metadata,
			'config'   => $config,
			'template' => $template,
		];
		// Only set lang when explicitly provided; empty string would incorrectly write wgLanguageCode=''
		$lang = $this->getOption( 'lang' );
		if ( $lang !== null ) {
			$options['lang'] = $lang;
		}

		if ( $this->manager->getStore()->getInstanceByPath( $path ) ) {
			$this->fatalError( "An instance with path \"$path\" already exists." );
		}

		$this->output( "Creating instance \"$path\"...\n" );

		try {
			$pid = $this->manager->createInstance( $path, $displayName, $options );
		} catch ( Throwable $ex ) {
			$this->fatalError( 'Failed to start creation process: ' . $ex->getMessage() );
		}

		$instanceUrl = $this->manager->getUrlForNewInstance( $path );
		$this->output( "Instance URL : $instanceUrl\n" );
		$this->output( "Process ID   : $pid\n\n" );

		$this->waitForProcess( $pid );
	}

	/**
	 * @param string $pid
	 * @return void
	 * @throws MaintenanceFatalError
	 */
	private function waitForProcess( string $pid ): void {
		$process = $this->processManager->getProcessInfo( $pid );
		if ( !$process ) {
			$this->fatalError( "Process $pid failed to register." );
		}

		$doneSteps = [];
		while ( $process->getExitCode() === null ) {
			foreach ( $process->getStepProgress() as $step => $status ) {
				if ( $status === 'completed' && !in_array( $step, $doneSteps ) ) {
					$this->output( "  ✓ $step\n" );
					$doneSteps[] = $step;
				}
			}
			sleep( 2 );
			$process = $this->processManager->getProcessInfo( $pid );
		}

		// Flush any remaining completed steps
		foreach ( $process->getStepProgress() as $step => $status ) {
			if ( $status === 'completed' && !in_array( $step, $doneSteps ) ) {
				$this->output( "  ✓ $step\n" );
			}
		}

		$state = $process->getState();
		$exitCode = $process->getExitCode();
		if ( $exitCode === 0 ) {
			$this->output( "\nDone. Instance created successfully (state: $state).\n" );
		} else {
			$this->fatalError( "Process finished with errors (state: $state, exit code: $exitCode)." );
		}
	}

	/**
	 * @param string $optionName
	 * @param array $default
	 * @return array
	 * @throws MaintenanceFatalError
	 */
	private function parseJsonOption( string $optionName, array $default ): array {
		$raw = $this->getOption( $optionName, '' );
		if ( !$raw ) {
			return $default;
		}
		$decoded = json_decode( $raw, true );
		if ( !is_array( $decoded ) ) {
			$this->fatalError( "Invalid JSON for --$optionName: $raw" );
		}
		return $decoded;
	}

	/**
	 * @param string $template
	 * @return string Resolved absolute path, or empty string if no template given
	 * @throws MaintenanceFatalError
	 */
	private function resolveTemplate( string $template ): string {
		if ( !$template ) {
			return '';
		}
		try {
			$provider = new InstanceTemplateProvider( $this->getServiceContainer()->getMainConfig() );
			$resolved = $provider->getTemplateSource( $template );
		} catch ( Throwable $ex ) {
			$this->fatalError( "Invalid template \"$template\": " . $ex->getMessage() );
		}
		if ( !file_exists( $resolved ) ) {
			$this->fatalError( "Template source file does not exist: $resolved" );
		}
		return $resolved;
	}
}

$maintClass = CreateInstance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
