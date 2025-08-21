<?php

namespace BlueSpice\WikiFarm;

use MediaWiki\Config\Config;

abstract class CommandDescriptionBase implements ICommandDescription {

	/**
	 *
	 * @var string
	 */
	protected $instanceName = '';

	/**
	 *
	 * @var Config
	 */
	protected $config = null;

	/**
	 *
	 * @var string
	 */
	protected $phpCli = '/usr/bin/php';

	/**
	 *
	 * @param string $instanceName
	 * @param Config $config
	 */
	public function __construct( $instanceName, $config ) {
		$this->instanceName = $instanceName;
		$this->config = $config;

		if ( $this->config->has( 'phpCli' ) ) {
			$this->phpCli = $this->config->get( 'phpCli' );
		}
	}

	/**
	 *
	 * @param string $instanceName
	 * @param Config $config
	 * @return ICommandDescription
	 */
	public static function factory( $instanceName, $config ) {
		return new static( $instanceName, $config );
	}

	/**
	 *
	 * @return string
	 */
	public function getCommandPathname() {
		return $this->phpCli;
	}

	/**
	 *
	 * @return int
	 */
	public function getPosition() {
		return 100;
	}

	/**
	 *
	 * @return bool
	 */
	public function runAsync() {
		return false;
	}

	/**
	 *
	 * @param string $instanceName
	 * @return bool
	 */
	public function shouldRun( $instanceName ) {
		return true;
	}

	/**
	 * @param string $name
	 * @param string $type
	 * @return string
	 */
	protected function buildMaintenancePath( $name, $type = 'extension' ) {
		$typePath = 'extensions';
		if ( $type === 'skin' ) {
			$typePath = 'skins';
		}
		$path = $GLOBALS['IP'] . "/$typePath/$name/maintenance";

		return $path;
	}

}
