<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\InstanceVaultMirrorIterator;
use PHPUnit\Framework\TestCase;

class InstanceVaultMirrorIteratorTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceVaultMirrorIterator
	 */
	public function testIteration() {
		$dummyInstanceVaultPath = __DIR__
			. '/data/InstanceVaultMirrorIterator/_sf_instances/Test-1';

		$expectedList = [
			$dummyInstanceVaultPath . '/CREATEDATE',
			$dummyInstanceVaultPath . '/cache',
			$dummyInstanceVaultPath . '/extensions',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/config',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/config/gm-settings.php',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/config/nm-settings.php',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/config/pm-settings.20191030120000.php',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/config/pm-settings.php',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/data',
			$dummyInstanceVaultPath . '/extensions/BlueSpiceFoundation/data/runJobsTriggerData.json',
			$dummyInstanceVaultPath . '/images',
			$dummyInstanceVaultPath . '/images/0',
			$dummyInstanceVaultPath . '/images/0/07',
			$dummyInstanceVaultPath . '/images/0/07/steve_jobs.png',
			$dummyInstanceVaultPath . '/images/8',
			$dummyInstanceVaultPath . '/images/8/8d',
			$dummyInstanceVaultPath . '/images/8/8d/sergey_brin2.jpg',
			$dummyInstanceVaultPath . '/images/bluespice',
			$dummyInstanceVaultPath . '/images/temp',
			$dummyInstanceVaultPath . '/images/thumb',
			$dummyInstanceVaultPath . '/LocalSettings.custom.php',
			$dummyInstanceVaultPath . '/meta.json'
		];

		$instanceVaultMirror = new InstanceVaultMirrorIterator( $dummyInstanceVaultPath );

		$actualList = [];
		foreach ( $instanceVaultMirror as $fileToClone ) {
			/** @var $file SplFileInfo */
			$actualList[] = $fileToClone->getPathname();
		}

		sort( $expectedList );
		sort( $actualList );

		$this->assertEquals( $expectedList, $actualList );
	}
}
