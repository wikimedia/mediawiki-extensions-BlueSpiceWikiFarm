<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\SettingsReader;
use PHPUnit\Framework\TestCase;

class SettingsReaderTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\SettingsReader::getConfig
	 */
	public function testGetConfig() {
		$dummyFilepath = __DIR__ . '/data/SettingsReaderTest/LocalSettings.php';
		$reader = new SettingsReader( $dummyFilepath );

		$config = $reader->getConfig();
		$this->assertInstanceOf( 'Config', $config );
		$this->assertEquals( 'vector', $config->get( 'DefaultSkin' ) );
		$this->assertFalse( $config->get( 'EnableUploads' ) );
		$this->assertEquals( CACHE_NONE, $config->get( 'MainCacheType' ) );
		$this->assertIsArray( $config->get( 'MemCachedServers' ) );
		$this->assertEquals( 7, $config->get( 'ArbitraryInt' ) );
		$this->assertEquals( 6.0, $config->get( 'ArbitraryFloat' ) );

		$config2 = $reader->getConfig( 'wgDB' );
		$this->assertEquals( 'mysql', $config2->get( 'type' ) );
	}
}
