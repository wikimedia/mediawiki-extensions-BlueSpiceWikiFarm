<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Message\Message;
use Symfony\Component\Process\Process;

abstract class RunMaintenanceScript extends InstanceAwareStep {

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

	/**
	 * @param array $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		$process = new Process( array_merge(
			[
				$this->getPhpExecutable(), $this->getFullScriptPath(),
			],
			$this->getFormattedArgs( $data )
		) );
		$this->getInstanceManager()->getLogger()->debug(
			'Running maintenance script: {cmd}', [
				'cmd' => $process->getCommandLine()
			]
		);
		$process->setTimeout( null );

		$this->modifyProcess( $process );
		$this->doExecuteProcess( $process );
		if ( $process->isSuccessful() ) {
			return array_merge(
				$data,
				[ 'success' => true ],
				$this->getDataForNextStep( $process->getOutput() )
			);
		}
		throw new Exception( Message::newFromKey( 'wikifarm-error-unknown' )->text() );
	}

	/**
	 * @param Process $process
	 * @return void
	 */
	protected function doExecuteProcess( Process $process ) {
		$process->run( function ( $type, $buffer ): void {
			if ( Process::ERR === $type ) {
				$this->getInstanceManager()->getLogger()->error(
					'Script failed: {error}', [
						'error' => $buffer
					]
				);
			} else {
				$this->getInstanceManager()->getLogger()->debug( $buffer );
			}
		} );
	}

	/**
	 * @return string|null
	 */
	protected function getPhpExecutable() {
		return $this->mainConfig->get( 'PhpCli' );
	}

	/**
	 * @return string
	 */
	protected function getFullScriptPath() {
		return $GLOBALS['IP'] .
			'/extensions/BlueSpiceWikiFarm/maintenance/workflow/' .
			ltrim( $this->getScriptPath(), '/' );
	}

	/**
	 * @param array $previousStepData
	 *
	 * @return array
	 */
	abstract protected function getFormattedArgs( array $previousStepData ): array;

	/**
	 * Path to the script file, relative to $IP
	 * @return string
	 */
	abstract protected function getScriptPath(): string;

	/**
	 * @param string $output Output of the script
	 *
	 * @return array
	 */
	protected function getDataForNextStep( string $output ): array {
		return [];
	}

	/**
	 * @param Process $process
	 */
	protected function modifyProcess( Process $process ) {
		// STUB
	}
}
