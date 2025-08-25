<?php

namespace BlueSpice\WikiFarm;

abstract class DynamicConfigurationBase implements IDynamicConfiguration {

	/**
	 *
	 * @var string
	 */
	protected $instanceName = '';

	/**
	 *
	 * @param string $instanceName
	 */
	public function __construct( $instanceName ) {
		$this->instanceName = $instanceName;
	}

	/**
	 *
	 * @param string $instanceName
	 * @return IDynamicConfiguration
	 */
	public static function factory( $instanceName ) {
		return new static( $instanceName );
	}

	/**
	 * Applies the configuration
	 * @return void
	 */
	public function apply() {
		if ( $this->skipProcessing() ) {
			return;
		}

		$this->doApply();
	}

	/**
	 * Modify configuration
	 */
	abstract protected function doApply();

	/**
	 * Wheter to actually apply the configuration
	 * @return bool
	 */
	protected function skipProcessing() {
		return false;
	}

}
