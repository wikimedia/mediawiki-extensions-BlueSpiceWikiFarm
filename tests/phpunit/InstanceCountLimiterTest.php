<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Config\Config;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter
 */
class InstanceCountLimiterTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter::canCreate
	 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter::isLimited
	 * @return void
	 */
	public function testNoLimit() {
		$configMock = $this->createMock( Config::class );
		$configMock->method( 'get' )->willReturn( null );

		$instanceStoreMock = $this->createMock( InstanceStore::class );
		$instanceStoreMock->method( 'getInstanceIds' )->willReturn( [ 1, 2, 3 ] );
		$instanceStoreMock->method( 'getInstanceById' )->willReturnCallback( function ( $id ) {
			$instanceMock = $this->createMock( InstanceEntity::class );
			$instanceMock->method( 'isActive' )->willReturn( $id > 1 );
			return $instanceMock;
		} );

		$limiter = new InstanceCountLimiter( $configMock, $instanceStoreMock );
		$this->assertTrue( $limiter->canCreate() );
		$this->assertFalse( $limiter->isLimited() );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter::canCreate
	 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter::isLimited
	 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter::getCurrentActiveCount
	 * @covers \BlueSpice\WikiFarm\InstanceCountLimiter::getLimit
	 * @dataProvider provideLimitData
	 * @return void
	 */
	public function testLimit( int $limit, int $activeCount, bool $canCreate ) {
		$configMock = $this->createMock( Config::class );
		$configMock->method( 'get' )->willReturn( $limit );

		$instanceStoreMock = $this->createMock( InstanceStore::class );
		$instanceStoreMock->method( 'getInstanceIds' )->willReturnCallback( static function () use ( $activeCount ) {
			return range( 1, $activeCount );
		} );
		$instanceStoreMock->method( 'getInstanceById' )->willReturnCallback( function ( $id ) {
			$instanceMock = $this->createMock( InstanceEntity::class );
			$instanceMock->method( 'isActive' )->willReturn( true );
			return $instanceMock;
		} );

		$limiter = new InstanceCountLimiter( $configMock, $instanceStoreMock );
		$this->assertSame( $limit, $limiter->getLimit() );
		$this->assertSame( $activeCount, $limiter->getCurrentActiveCount() );
		$this->assertSame( $canCreate, $limiter->canCreate() );
		$this->assertTrue( $limiter->isLimited() );
	}

	/**
	 * @return array
	 */
	public function provideLimitData(): array {
		return [
			'limit-not-reached' => [
				'limit' => 3,
				'activeCount' => 2,
				'canCreate' => true
			],
			'limit-reached' => [
				'limit' => 3,
				'activeCount' => 3,
				'canCreate' => false
			],
			'limit-exceeded' => [
				'limit' => 3,
				'activeCount' => 4,
				'canCreate' => false
			]
		];
	}
}
