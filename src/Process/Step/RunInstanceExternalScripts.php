<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\ICommandDescription;
use BlueSpice\WikiFarm\InstanceManager;
use Cocur\BackgroundProcess\BackgroundProcess;
use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\MultiConfig;
use MediaWiki\Registration\ExtensionRegistry;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

abstract class RunInstanceExternalScripts extends InstanceAwareStep {

	/** @var Config */
	protected $mainConfig;

	/**
	 * @param InstanceManager $instanceManager
	 * @param Config $mainConfig
	 * @param string $instanceId
	 * @throws Exception
	 */
	public function __construct( InstanceManager $instanceManager, Config $mainConfig, string $instanceId ) {
		parent::__construct( $instanceManager, $instanceId );
		$this->mainConfig = $mainConfig;
	}

	/** @inheritDoc */
	public function execute( $data = [] ): array {
		$factories = ExtensionRegistry::getInstance()->getAttribute( $this->getActionAttributeName() );

		$commandDescriptions = [];
		foreach ( $factories as $factoryKey => $factoryCallback ) {
			if ( !is_callable( $factoryCallback ) ) {
				$this->getInstanceManager()->getLogger()->error(
					"RunInstanceExternalScripts: Callback for {factoryKey} not callable in {attribute}!",
					[
						'factoryKey' => $factoryKey,
						'attribute' => $this->getActionAttributeName()
					]
				);
				throw new Exception( "wikifarm-error-unknown" );
			}

			$commandDescription = call_user_func_array(
				$factoryCallback,
				[
					$this->instance->getId(),
					new MultiConfig( [
						$this->instanceManager->getFarmConfig(),
						new HashConfig( [
							'phpCli' => $this->mainConfig->get( 'PhpCli' )
						] )
					] )
				]
			);

			if ( $commandDescription instanceof ICommandDescription === false ) {
				$this->getInstanceManager()->getLogger()->error(
					"RunInstanceExternalScripts: Callback for {factoryKey} did not produce object of type {instance}",
					[
						'factoryKey' => $factoryKey,
						'instance' => ICommandDescription::class
					]
				);
				throw new Exception( "wikifarm-error-unknown" );
			}
			$commandDescriptions[] = $commandDescription;
		}

		usort( $commandDescriptions, static function ( $a, $b ) {
			return $a->getPosition() > $b->getPosition();
		} );

		foreach ( $commandDescriptions as $desc ) {
			$commandLine = $this->buildCommandline( $desc );
			try {
				if ( $desc->runAsync() ) {
					$process = new BackgroundProcess( implode( ' ', $commandLine ) );
					$process->run();
				} else {
					$process = new Process( $commandLine );
					$process->setTimeout( (int)ini_get( 'max_execution_time' ) );
					$process->run();
					$this->getInstanceManager()->getLogger()->debug(
						"RunInstanceExternalScripts: Execution result for {command} was {output}",
						[
							'command' => implode( ' ', $commandLine ),
							'output' => $process->getOutput()
						]
					);
				}
			} catch ( ProcessFailedException $ex ) {
				$this->getInstanceManager()->getLogger()->error(
					'RunInstanceExternalScripts: Command "{cmd}" with "{error}"', [
						'cmd' => $process ? $process->getCommandLine() : '<unknown command>',
						'error' => $ex->getMessage()
					]
				);
			}
		}

		return array_merge( $data, [ 'success' => true ] );
	}

	/**
	 * @return string
	 */
	abstract protected function getActionAttributeName();

	/**
	 *
	 * @param ICommandDescription $desc
	 * @return array
	 */
	private function buildCommandline( $desc ) {
		$commandLine = [];
		$commandLine[] = $desc->getCommandPathname();

		foreach ( $desc->getCommandArguments() as $arg ) {
			$commandLine[] = $arg;
		}

		$commandLine[] = '--sfr';
		$commandLine[] = $this->instance->getId();

		return $commandLine;
	}
}
