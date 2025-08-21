<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\DirectInstanceStore;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use BlueSpice\WikiFarm\RootInstanceEntity;
use DateTime;
use InvalidArgumentException;
use MediaWiki\Config\Config;
use PHPUnit\Framework\TestCase;
use Random\RandomException;
use Wikimedia\Rdbms\Database;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \BlueSpice\WikiFarm\InstanceStore
 * @covers \BlueSpice\WikiFarm\DirectInstanceStore
 */
class InstanceStoreTest extends TestCase {
	/**
	 * @covers \BlueSpice\WikiFarm\InstanceStore::store
	 * @dataProvider provideStoreData
	 * @param InstanceEntity $entity
	 * @param bool $queryRes
	 * @param array $expected
	 * @return void
	 */
	public function testStore( InstanceEntity $entity, bool $queryRes, array $expected ) {
		$dbMock = $this->createMock( Database::class );
		$dbMock->expects( $this->once() )->method( $expected['method'] )->with( ...$expected['data'] );
		$dbMock->method( 'tableExists' )->willReturn( true );

		$queryResMock = $queryRes ? new FakeResultWrapper( [
			(object)$entity->dbSerialize()
		] ) : new FakeResultWrapper( [] );
		$selectQueryMock = $this->createMock( \Wikimedia\Rdbms\SelectQueryBuilder::class );
		$selectQueryMock->expects( $this->exactly( 2 ) )->method( 'from' )
			->with( 'simple_farmer_instances' )
			->willReturnSelf();
		$selectQueryMock->expects( $this->exactly( 2 ) )->method( 'select' )
			->with( '*' )
			->willReturnSelf();
		$selectQueryMock->expects( $this->once() )->method( 'where' )
			->willReturnSelf();
		$selectQueryMock->expects( $this->exactly( 2 ) )->method( 'caller' )
			->willReturnSelf();
		$selectQueryMock->expects( $this->once() )->method( 'fetchResultSet' )
			->willReturn( $queryResMock );
		$dbMock->method( 'newSelectQueryBuilder' )->willReturn( $selectQueryMock );

		$managementDBFactoryMock = $this->createMock( ManagementDatabaseFactory::class );
		$managementDBFactoryMock->method( 'createManagementConnection' )->willReturn( $dbMock );

		$store = new InstanceStore( $managementDBFactoryMock );
		$store->store( $entity );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\InstanceStore::getInstanceById
	 * @covers \BlueSpice\WikiFarm\InstanceStore::getInstanceByPath
	 * @covers \BlueSpice\WikiFarm\InstanceStore::getInstanceByIdOrPath
	 * @return void
	 */
	public function testGetInstance() {
		$queryResMock = new FakeResultWrapper( [ (object)$this->getEntity()->dbSerialize() ] );
		$dbMock = $this->createMock( Database::class );
		$selectQueryMock = $this->createMock( \Wikimedia\Rdbms\SelectQueryBuilder::class );
		$selectQueryMock->method( 'from' )->willReturnSelf();
		$selectQueryMock->method( 'select' )->willReturnSelf();
		$selectQueryMock->method( 'where' )->willReturnSelf();
		$selectQueryMock->method( 'caller' )->willReturnSelf();
		$selectQueryMock->expects( $this->once() )->method( 'fetchResultSet' )->willReturn( $queryResMock );
		$dbMock->expects( $this->once() )->method( 'newSelectQueryBuilder' )->willReturn( $selectQueryMock );

		$dbMock->method( 'tableExists' )->willReturn( true );

		$managementDBFactoryMock = $this->createMock( ManagementDatabaseFactory::class );
		$managementDBFactoryMock->method( 'createManagementConnection' )->willReturn( $dbMock );

		$store = new InstanceStore( $managementDBFactoryMock );
		$instance = $store->getInstanceById( '1234567891123456' );
		$this->assertInstanceOf( InstanceEntity::class, $instance );
		$this->assertSame( 'dummyPath', $instance->getPath() );
		$this->assertNull( $store->getInstanceById( '123' ) );

		$instance = $store->getInstanceByPath( 'dummyPath' );
		$this->assertInstanceOf( InstanceEntity::class, $instance );
		$this->assertSame( 'dummyPath', $instance->getPath() );

		$instance = $store->getInstanceByPath( 'w' );
		$this->assertInstanceOf( RootInstanceEntity::class, $instance );

		$instance = $store->getInstanceByIdOrPath( 'dummyPath' );
		$this->assertInstanceOf( InstanceEntity::class, $instance );
		$this->assertSame( 'dummyPath', $instance->getPath() );
	}

	/**
	 * @return void
	 */
	public function getRemoveEntry() {
		$dbMock = $this->createMock( IDatabase::class );
		$dbMock->expects( $this->once() )->method( 'delete' )->with(
			'simple_farmer_instances',
			[ 'sfi_id' => 1234567891123456 ],
			DirectInstanceStore::class . '::removeEntry'
		);
	}

	/**
	 * @dataProvider provideEmptyData
	 * @covers       \BlueSpice\WikiFarm\InstanceStore::newEmptyInstance
	 * @covers       \BlueSpice\WikiFarm\InstanceStore::generateId
	 * @return void
	 * @throws RandomException
	 */
	public function testNewEmptyInstance( string $path, array $config, ?array $expected ) {
		$dbMock = $this->createMock( IDatabase::class );
		$managementDBFactoryMock = $this->createMock( ManagementDatabaseFactory::class );
		$managementDBFactoryMock->method( 'createManagementConnection' )->willReturn( $dbMock );
		$store = new InstanceStore( $managementDBFactoryMock );
		if ( $expected === null ) {
			$this->expectException( InvalidArgumentException::class );
		}
		$configMock = $this->createMock( Config::class );
		$configMock->method( 'get' )->willReturnCallback( static function ( $key ) use ( $config ) {
			return $config[$key] ?? null;
		} );
		$instance = $store->newEmptyInstance( $path, $configMock );
		if ( is_array( $expected ) ) {
			$this->assertInstanceOf( InstanceEntity::class, $instance );
			$this->assertNotEmpty( preg_match( $expected['db'], $instance->getDbName() ) );
			$this->assertNotEmpty( preg_match( $expected['prefix'], $instance->getDbPrefix() ) );
		}
	}

	public function provideEmptyData() {
		return [
			'valid-shared' => [
				'path' => 'dummyPath',
				'config' => [
					'useSharedDB' => true,
					'sharedDBname' => 'dummySharedDBName',
				],
				'expected' => [
					'db' => '/dummySharedDBName/',
					'prefix' => '/^.{8}_$/',
				]
			],
			'valid-separate' => [
				'path' => 'dummyPath',
				'config' => [
					'useSharedDB' => false,
					'sharedDBname' => 'dummySharedDBName',
					'dbPrefix' => 'sfr_'
				],
				'expected' => [
					'db' => '/^sfr_.{8}$/',
					'prefix' => '//',
				]
			]
		];
	}

	/**
	 * @return array[]
	 */
	public function provideStoreData(): array {
		$entity = $this->getEntity();
		$updateData = $entity->dbSerialize();
		unset( $updateData['sfi_id'] );

		return [
			'new-instance' => [
				'entity' => $entity,
				'queryRes' => false,
				'expected' => [
					'method' => 'insert',
					'data' => [
						'simple_farmer_instances',
						$entity->dbSerialize(),
						DirectInstanceStore::class . '::store'
					]
				]
			],
			'existing-instance' => [
				'entity' => $entity,
				'queryRes' => true,
				'expected' => [
					'method' => 'update',
					'data' => [
						'simple_farmer_instances',
						$updateData,
						[ 'sfi_id' => '1234567891123456' ],
						DirectInstanceStore::class . '::store'
					]
				]
			]
		];
	}

	/**
	 * @return InstanceEntity
	 */
	private function getEntity(): InstanceEntity {
		return new InstanceEntity(
			'1234567891123456',
			'dummyPath',
			'dummyName',
			DateTime::createFromFormat( 'YmdHis', '20240101101010' ),
			DateTime::createFromFormat( 'YmdHis', '20240101101011' ),
			InstanceEntity::STATUS_INIT,
			'dummyDbName',
			'dummyDbPrefix',
			[ 'group' => 'dummyGroup', 'keywords' => [ 'dummyKeyword' ], 'desc' => 'dummyDesc' ],
			[ 'dummyKey' => 'dummyValue' ]
		);
	}
}
