<?php

namespace BlueSpice\WikiFarm\Rest;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Message\Message;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\SimpleHandler;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use Psr\Log\LoggerInterface;
use Wikimedia\ParamValidator\ParamValidator;

class ProcessHandler extends SimpleHandler {

	/** @var ProcessManager */
	private $processManager;

	/** @var LoggerInterface */
	private $logger;

	/**
	 * @param ProcessManager $processManager
	 */
	public function __construct(
		ProcessManager $processManager
	) {
		$this->processManager = $processManager;
		$this->logger = LoggerFactory::getInstance( 'BlueSpiceWikiFarm' );
	}

	public function execute() {
		$this->assertRootCall();
		$pid = $this->getValidatedParams()['processId'];
		$processInfo = $this->processManager->getProcessInfo( $pid );
		if ( !$processInfo ) {
			throw new HttpException( 'Process not found' );
		}
		$steps = [];
		foreach ( array_keys( $processInfo->getSteps() ) as $step ) {
			$steps[$step] = $this->getStepLabel( $step );
		}

		$data = [
			'state' => 'running',
			'steps' => $steps
		];

		if ( $processInfo->getState() === 'interrupted' ) {
			$data['doneStep'] = $processInfo->getOutput()['lastStep'];
			$this->processManager->proceed( $pid );
			return $this->getResponseFactory()->createJson( $data );
		}
		if ( $processInfo->getState() === 'terminated' ) {
			if ( $processInfo->getExitCode() === 1 ) {
				$this->logger->error( "Process failed: {processId}\n{output}", [
					'processId' => $pid,
					'output' => implode( "\n", $processInfo->getOutput() )
				] );
			}
			$data['state'] = $processInfo->getExitStateMessage();
			$data['output'] = is_array( $processInfo->getOutput() ) ?
				implode( "\n", $processInfo->getOutput() ) : $processInfo->getOutput();
			return $this->getResponseFactory()->createJson( $data );
		}

		return $this->getResponseFactory()->createJson( $data );
	}

	/**
	 * @param string $step
	 *
	 * @return string
	 */
	private function getStepLabel( $step ) {
		// wikifarm-action-copyinstancedata-description
		// wikifarm-action-createinstancevault-description
		// wikifarm-action-installinstance-description
		// wikifarm-action-runpostinstancecrationcommands-description
		// wikifarm-action-runupdate-description
		// wikifarm-action-archiveinstance-description
		// wikifarm-action-copyuser-description
		// wikifarm-action-runpostinstancedeletioncommands-description
		// wikifarm-action-runpreinstancedeletioncommands-description
		$step = str_replace( '-', '', $step );
		return Message::newFromKey( "wikifarm-action-$step-description" )->text();
	}

	/**
	 * @return bool
	 */
	public function needsReadAccess() {
		return true;
	}

	/**
	 * @return array[]
	 */
	public function getParamSettings() {
		return [
			'processId' => [
				static::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			]
		];
	}

	/**
	 * @return void
	 * @throws HttpException
	 */
	private function assertRootCall() {
		if ( !defined( 'FARMER_IS_ROOT_WIKI_CALL' ) || !FARMER_IS_ROOT_WIKI_CALL ) {
			throw new HttpException( 'This call is only available from the root instance', 409 );
		}
	}
}
