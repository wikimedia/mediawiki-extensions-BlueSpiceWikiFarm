<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\InstanceCountLimiter;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstancePathGenerator;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\Process\ArchiveInstance;
use BlueSpice\WikiFarm\Process\CloneInstance;
use BlueSpice\WikiFarm\Process\CreateInstance;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use MWStake\MediaWiki\Component\ProcessManager\ProcessManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\Rdbms\DatabaseFactory;

/**
 * @covers \BlueSpice\WikiFarm\InstanceManager
 */
class InstanceManagerTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceManager::createInstance
	 * @dataProvider provideLimitData
	 * @param bool $limitReached
	 * @return void
	 * @throws \Exception
	 */
	public function testCreate( bool $limitReached ) {
		$store = $this->createMock( InstanceStore::class );
		$store->expects( $limitReached ? $this->never() : $this->once() )->method( 'store' );
		$manager = $this->getManager( CreateInstance::class, $limitReached, $store );
		if ( $limitReached ) {
			$this->expectException( InvalidArgumentException::class );
		}
		$manager->createInstance( 'dummyPath', 'dummy' );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceManager::cloneInstance
	 * @dataProvider provideLimitData
	 * @param bool $limitReached
	 * @return void
	 * @throws \Exception
	 */
	public function testClone( bool $limitReached ) {
		$store = $this->createMock( InstanceStore::class );
		$store->expects( $limitReached ? $this->never() : $this->once() )->method( 'store' );
		$manager = $this->getManager( CloneInstance::class, $limitReached, $store );
		if ( $limitReached ) {
			$this->expectException( InvalidArgumentException::class );
		}
		$source = $this->createMock( InstanceEntity::class );
		$source->method( 'getStatus' )->willReturn( InstanceEntity::STATUS_READY );
		$manager->cloneInstance( 'dummyPath', $source, 'dummy' );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceManager::archiveInstance
	 * @return void
	 */
	public function testArchive() {
		$manager = $this->getManager( ArchiveInstance::class, false );
		$manager->archiveInstance( $this->createMock( InstanceEntity::class ) );
	}

	/**
	 * @return array
	 */
	protected function provideLimitData(): array {
		return [
			'unlimited' => [ false ],
			'limited' => [ true ]
		];
	}

	/**
	 * @param string $expectedProcess
	 * @param bool $limitReached
	 * @param InstanceStore|null $store
	 * @return InstanceManager
	 */
	private function getManager(
		string $expectedProcess, bool $limitReached, ?InstanceStore $store = null
	): InstanceManager {
		$store = $store ?: $this->createMock( InstanceStore::class );
		$processManagerMock = $this->createMock( ProcessManager::class );
		$processManagerMock->expects( $limitReached ? $this->never() : $this->once() )
			->method( 'startProcess' )->with(
				$this->callback( static function ( $process ) use ( $expectedProcess ) {
					return $process instanceof $expectedProcess;
				} )
			)->willReturn( '123' );
		$limiterMock = $this->createMock( InstanceCountLimiter::class );
		$limiterMock->method( 'isLimited' )->willReturn( $limitReached );
		$limiterMock->method( 'canCreate' )->willReturn( !$limitReached );
		$pathGeneratorMock = $this->createMock( InstancePathGenerator::class );
		$pathGeneratorMock->method( 'checkIfValid' )->willReturn( true );
		return new InstanceManager(
			$store,
			$processManagerMock,
			$this->createMock( LoggerInterface::class ),
			$this->createMock( Config::class ),
			$this->createMock( Config::class ),
			$this->createMock( DatabaseFactory::class ),
			$limiterMock,
			$pathGeneratorMock
		);
	}
}
