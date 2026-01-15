<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\Storage\InstanceTransaction;
use PHPUnit\Framework\TestCase;
use Wikimedia\FileBackend\FSFileBackend;

/**
 * @covers \BlueSpice\WikiFarm\Storage\InstanceTransaction
 */
class InstanceTransactionTest extends TestCase {

	/**
	 * @param string $instance
	 * @param string $file
	 * @param string $expected
	 * @return void
	 * @covers \BlueSpice\WikiFarm\Storage\InstanceTransaction::makeInstancePath
	 * @dataProvider provideInstanceZoneData
	 */
	public function testInstancePaths( string $instance, string $file, string $expected ) {
		$backend = new FSFileBackend( [ 'name' => 'instance-backend', 'domainId' => 'test' ] );
		$transaction = new InstanceTransaction( $backend );

		$this->assertSame( $expected, $transaction->makeInstancePath( $instance, $file ) );
	}

	/**
	 * @param string $instance
	 * @param string $expected
	 * @return void
	 * @covers \BlueSpice\WikiFarm\Storage\InstanceTransaction::makeArchiveInstancePath
	 * @dataProvider provideInstanceArchiveZoneData
	 */
	public function testInstanceArchivePaths( string $instance, string $expected ) {
		$backend = new FSFileBackend( [ 'name' => 'archive-backend', 'domainId' => 'test' ] );
		$transaction = new InstanceTransaction( $backend );

		$this->assertSame( $expected, $transaction->makeArchiveInstancePath( $instance ) );
	}

	/**
	 * @return array[]
	 */
	protected function provideInstanceZoneData() {
		return [
			[ 'instance1', '', 'mwstore://instance-backend/instances-public/instance1' ],
			[ 'instance1', 'file.txt', 'mwstore://instance-backend/instances-public/instance1/file.txt' ],
			[ '/instance1/', '/file.txt', 'mwstore://instance-backend/instances-public/instance1/file.txt' ],
		];
	}

	/**
	 * @return array[]
	 */
	protected function provideInstanceArchiveZoneData() {
		return [
			[ 'archive1', 'mwstore://archive-backend/archive-public/archive1' ],
			[ '/archive1/', 'mwstore://archive-backend/archive-public/archive1' ],
		];
	}
}
