<?php

namespace BlueSpice\WikiFarm;

use RecursiveFilterIterator;
use SplFileInfo;

class InstanceVaultBlacklistFilter extends RecursiveFilterIterator {

	public function accept(): bool {
		$file = $this->getInnerIterator();
		if ( $this->isBlacklisted( $file ) ) {
			return false;
		}
		return true;
	}

	/**
	 *
	 * @param SplFileInfo $file
	 * @return bool
	 */
	private function isBlacklisted( $file ) {
		if ( $file->getFilename() === 'LocalSettings.php' ) {
			return true;
		}

		$path = $file->getPath();
		$basePathname = basename( $path );

		if ( $basePathname === 'cache' ) {
			return true;
		}
		if ( $basePathname === 'temp' ) {
			return true;
		}

		$twoLevelBasePathname = basename( dirname( $path ) ) . '/' . $basePathname;
		if ( $twoLevelBasePathname === 'images/thumb' ) {
			return true;
		}
		if ( $twoLevelBasePathname === 'images/bluespice' ) {
			return true;
		}

		return false;
	}
}
