<?php

namespace BlueSpice\WikiFarm;

class CliInstanceArgExtractor {
	/** @var bool */
	private $isProcessed = false;
	/** @var array */
	private $args = [];
	/** @var array */
	private $originalArgs;

	/**
	 * @param array &$args
	 */
	public function __construct( &$args ) {
		$this->originalArgs =& $args;
	}

	/**
	 *
	 * @return string
	 */
	public function extractInstanceIdentifier(): string {
		if ( $this->isProcessed ) {
			return $this->args['sfr'] ?? '';
		}

		$this->processArgs();
		return $this->extractInstanceIdentifier();
	}

	/**
	 *
	 * @return bool
	 */
	public function extractIsQuiet(): bool {
		if ( $this->isProcessed ) {
			return $this->args['quiet'] ?? false;
		}

		$this->processArgs();
		return $this->extractIsQuiet();
	}

	/**
	 *
	 * @return void
	 */
	private function processArgs() {
		$isSfrArg = false;
		$newArgv = [];
		foreach ( $this->originalArgs as $argVal ) {
			// Case "--sfr <instancename>"
			if ( $argVal === '--sfr' ) {
				$isSfrArg = true;
				continue;
			}
			if ( $isSfrArg ) {
				$this->args['sfr'] = $argVal;
				$isSfrArg = false;
				continue;
			}

			// Case "--sfr=<instancename>" (and similar)
			if ( strpos( $argVal, '--sfr' ) === 0 ) {
				$parts = explode( '=', $argVal, 2 );
				if ( count( $parts ) !== 2 ) {
					continue;
				}
				$parts = array_map( static function ( $val ) {
					$val = trim( $val );
					$val = trim( $val, '"' );
					$val = trim( $val );
					return $val;
				}, $parts );

				$this->args['sfr'] = $parts[1];
				continue;
			}
			if ( $argVal === '--farm-quiet' ) {
				$this->args['quiet'] = true;
				continue;
			}
			$newArgv[] = $argVal;
		}
		$this->originalArgs = $newArgv;
		$this->isProcessed = true;
	}
}
