<?php

namespace BlueSpice\WikiFarm;

use Exception;

class InstancePathGenerator {
	public const PATH_LENGTH = 10;
	// Max length for a path explicitly provided by the user
	private const PATH_USER_LENGHT = 255;

	private const PATH_PATTERN = '/^[a-zA-Z0-9_-]*$/';
	private const PATH_PATTERN_ALLOW_SUB = '/^[a-zA-Z0-9_\-\/]*$/';

	private const GENERATORS = [
		'firstLetters',
		'firstLast',
		'truncate'
	];

	/** @var InstanceStore */
	private $store;

	/**
	 * @param DirectInstanceStore $store
	 */
	public function __construct( DirectInstanceStore $store ) {
		$this->store = $store;
	}

	/**
	 * @param string $name
	 * @return string
	 * @throws Exception
	 */
	public function generateFromName( string $name ) {
		// Strip non-URL-safe characters
		$path = preg_replace( '/[^ a-zA-Z0-9_-]/', '', $name );
		// Trim path and replace leading/trailing underscores
		$path = trim( $path, '_ ' );
		if ( $path === '' ) {
			throw new Exception( 'Invalid name' );
		}
		if ( $this->checkIfValid( $path ) ) {
			// If user already typed a path that is valid, use it
			return $path;
		}
		$validFallback = null;
		foreach ( static::GENERATORS as $generator ) {
			$test = $this->$generator( $path );
			if ( $this->checkIfValid( $test ) ) {
				if ( strlen( $test ) < 2 ) {
					// We don't prefer single letter paths, check if we can use any other
					$validFallback = $test;
				} else {
					return $test;
				}
			}
		}
		return $validFallback ?: $this->fallback( $path );
	}

	/**
	 * @param string $path
	 * @param bool $userInput
	 * @return bool
	 */
	public function checkIfValid( string $path, bool $userInput = false ): bool {
		$maxLength = $userInput ? static::PATH_USER_LENGHT : static::PATH_LENGTH;
		return strlen( $path ) > 0 &&
			strlen( $path ) < $maxLength + 1 &&
			preg_match( self::PATH_PATTERN, $path ) === 1 &&
			$this->store->pathAvailable( $path );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function firstLetters( string $path ): string {
		// Explode on ' ', '_', and '-', but also on uppercase letter in case of camel-case
		$words = preg_split( '/(?=[A-Z])|[_-]| /', $path );
		$letters = '';
		foreach ( $words as $word ) {
			$word = trim( $word );
			if ( empty( $word ) ) {
				continue;
			}
			$letters .= substr( $word, 0, 1 );
		}
		return substr( mb_strtoupper( $letters ), 0, static::PATH_LENGTH );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function firstLast( string $path ): string {
		// Remove whitespace
		$path = preg_replace( '/\s+/', '', $path );
		if ( strlen( $path ) < 2 ) {
			return $path;
		}
		$first = substr( $path, 0, 1 );
		$last = substr( $path, -1 );
		return mb_strtoupper( $first . $last );
	}

	/**
	 * @param string $path
	 * @return string
	 */
	private function truncate( string $path ): string {
		if ( empty( $path ) ) {
			return '';
		}
		return substr( strtoupper( $path ), 0, static::PATH_LENGTH );
	}

	/**
	 * @param string $path
	 * @return string
	 * @throws Exception
	 */
	private function fallback( string $path ): string {
		$path = $this->truncate( $path );
		$path = substr( $path, 0, static::PATH_LENGTH - 2 );
		$attempts = 0;
		while ( $attempts < 100 ) {
			$attempts++;
			$path .= $attempts;
			if ( $this->checkIfValid( $path ) ) {
				return $path;
			}
		}
		throw new Exception( 'Could not generate a valid path' );
	}
}
