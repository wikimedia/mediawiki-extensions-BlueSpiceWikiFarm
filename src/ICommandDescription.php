<?php

namespace BlueSpice\WikiFarm;

interface ICommandDescription {

	/**
	 * @return string The full file system path to the command
	 */
	public function getCommandPathname();

	/**
	 * @return array An array of flags/arguments/options to be passed to the command. The `--srf`
	 * argument does not need to be included
	 */
	public function getCommandArguments();

	/**
	 * Whether or not the execution of this command should block further execution of the
	 * application
	 * @return bool
	 */
	public function runAsync();

	/**
	 * Whether or not the command should actually be excuted
	 * @param string $instanceName
	 * @return bool
	 */
	public function shouldRun( $instanceName );

	/**
	 * An integer to sort the execution order
	 * @return int
	 */
	public function getPosition();
}
