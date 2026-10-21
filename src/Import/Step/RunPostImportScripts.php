<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceManager;
use JsonException;
use MediaWiki\Config\Config;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;
use RuntimeException;
use Symfony\Component\Process\Process;

class RunPostImportScripts extends ImportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param Config $mainConfig
	 * @param HookContainer $hookContainer
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		private readonly Config $mainConfig,
		private readonly HookContainer $hookContainer,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager );
		$scripts = $this->getDefaultScripts();
		$this->hookContainer->run(
			'BlueSpiceWikiFarmImportRunPostImportScripts',
			[ &$scripts, $instance ]
		);

		$executedScripts = [];
		foreach ( $scripts as $scriptDefinition ) {
			[ $script, $args ] = $this->normalizeScriptDefinition( $scriptDefinition );
			$command = [
				$this->mainConfig->get( 'PhpCli' ),
				$GLOBALS['IP'] . '/maintenance/run.php',
				$script,
				...$args,
				'--sfr',
				$instance->getId(),
			];
			$process = new Process( $command );
			$process->setTimeout( null );
			$process->run( function ( $type, $buffer ): void {
				if ( Process::ERR === $type ) {
					$this->logger->error( trim( $buffer ) );
					return;
				}
				$this->logger->debug( trim( $buffer ) );
			} );

			if ( !$process->isSuccessful() ) {
				$this->logger->error( 'RunPostImportScripts: Script failed', [
					'script' => $script,
					'args' => $args,
					'error' => $process->getErrorOutput(),
				] );
				continue;
			}
			$executedScripts[] = [ 'script' => $script, 'args' => $args ];
		}

		$this->addToData( 'postImportScripts', $executedScripts );
	}

	/**
	 * @return array<int,array{script:string,args:array<int,string>}>
	 */
	private function getDefaultScripts(): array {
		return [
			[
				'script' => 'update.php',
				'args' => [ '--quick', '--skip-config-validation' ],
			],
			[
				'script' => 'rebuildall.php',
				'args' => [],
			]
		];
	}

	/**
	 * @param mixed $scriptDefinition
	 * @return array{0:string,1:array<int,string>}
	 */
	private function normalizeScriptDefinition( mixed $scriptDefinition ): array {
		if ( is_string( $scriptDefinition ) ) {
			return [ $scriptDefinition, [] ];
		}
		if ( !is_array( $scriptDefinition ) ) {
			throw new RuntimeException( 'Invalid post-import script definition: script must be string or array' );
		}

		$script = $scriptDefinition['script'] ?? null;
		$args = $scriptDefinition['args'] ?? [];
		if ( !is_string( $script ) || $script === '' ) {
			throw new RuntimeException( 'Invalid post-import script definition: missing script name' );
		}
		if ( !is_array( $args ) ) {
			throw new RuntimeException( "Invalid post-import script definition for {$script}: args must be array" );
		}

		$normalizedArgs = [];
		foreach ( $args as $arg ) {
			$normalizedArgs[] = (string)$arg;
		}

		return [ $script, $normalizedArgs ];
	}
}
