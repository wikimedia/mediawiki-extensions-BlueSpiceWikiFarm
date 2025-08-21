<?php

namespace BlueSpice\WikiFarm;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class InstanceVaultMirrorIterator extends RecursiveIteratorIterator {

	/**
	 *
	 * @param string $source
	 */
	public function __construct( $source ) {
		$dir = new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS );
		$filter = new InstanceVaultBlacklistFilter( $dir );

		parent::__construct( $filter, RecursiveIteratorIterator::SELF_FIRST );
	}
}
