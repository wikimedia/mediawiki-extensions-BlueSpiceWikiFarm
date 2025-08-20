<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\Config;
use MediaWiki\Config\HashConfig;
use MediaWiki\MediaWikiServices;
use ParseError;
use Wikimedia\AtEase\AtEase;

class SettingsReader {

	/**
	 *
	 * @var string
	 */
	private $settingsPathname = '';

	/**
	 *
	 * @param string $settingsPathname
	 */
	public function __construct( $settingsPathname ) {
		$this->settingsPathname = $settingsPathname;
	}

	/**
	 *
	 * @param string $prefix
	 * @return Config
	 */
	public function getConfig( $prefix = 'wg' ) {
		$vars = $this->getVarsFromFile();
		$filteredVars = [];
		$prefixPattern = "#^$prefix#";

		foreach ( $vars as $varName => $varValue ) {
			$matches = preg_match( $prefixPattern, $varName );
			if ( $matches === false ) {
				continue;
			}
			$unprefixedVarName = preg_replace( $prefixPattern, '', $varName );
			$filteredVars[ $unprefixedVarName ] = $varValue;
		}

		$config = new HashConfig( $filteredVars );
		return $config;
	}

	/**
	 * @return array
	 */
	public function getArray() {
		return $this->getVarsFromFile();
	}

	/**
	 *
	 * @var array
	 */
	private $vars = [];

	/**
	 *
	 * @var bool
	 */
	private $fileLoaded = false;

	/**
	 * @return array
	 */
	private function getVarsFromFile() {
		if ( $this->fileLoaded ) {
			return $this->vars;
		}

		$content = file_get_contents( $this->settingsPathname );
		preg_replace_callback( '#^\$(.*?)=(.*?);#m', function ( $matches ) {
			$varName = trim( $matches[1] );
			$value = trim( $matches[2] );
			try {
				$value = $this->extractValue( $value );
			} catch ( ParseError $ex ) {
				return $matches[0];
			}
			$this->vars[ $varName ] = $value;
			return $matches[0];
		}, $content );

		return $this->vars;
	}

	/**
	 * @param string $value
	 * @return mixed
	 */
	private function extractValue( string $value ) {
		$trimmedValue = trim( $value, '"\'' );
		if ( is_numeric( $value ) ) {
			if ( strpos( $value, '.' ) !== false ) {
				return floatval( $value );
			} else {
				return intval( $value );
			}
		}
		if ( $trimmedValue === 'true' ) {
			return true;
		}
		if ( $trimmedValue === 'false' ) {
			return false;
		}
		if ( defined( $trimmedValue ) ) {
			return constant( $trimmedValue );
		}

		$expression = "\$dummy = $value;";
		AtEase::suppressWarnings();
		eval( $expression ); // phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.eval
		AtEase::restoreWarnings();

		return $dummy;
	}

	/**
	 *
	 * @param string $instanceName
	 * @return SettingsReader
	 */
	public static function newFromInstanceName( $instanceName ) {
		$farmConfig = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm._Config' );
		$vaultBaseDir = $farmConfig->get( 'instanceDirectory' );
		$settingsPathname = "$vaultBaseDir/$instanceName/LocalSettings.php";

		return new static( $settingsPathname );
	}

}
