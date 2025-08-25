<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\AccessStore;
use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\TeamQuery;
use BlueSpice\WikiFarm\DirectInstanceStore;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\TestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \BlueSpice\WikiFarm\AccessControl\AccessStore
 */
class AccesssStoreTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\AccessStore::userHasRoleOnInstance
	 */
	public function testUserHasRoleOnInstance() {
		$storeMock = $this->createMock( DirectInstanceStore::class );
		$storeMock->method( 'getInstancePathsQuick' )->willReturn( [ 'Test1', 'Test2' ] );

		$creator = new InstanceGroupCreator( $storeMock );

		$dbMock = $this->createMock( IDatabase::class );
		$dbMock->method( 'makeList' )->willReturnCallback( static function ( $list ) {
			return implode( ',', $list );
		} );
		$dbMock->expects( $this->exactly( 2 ) )
			->method( 'selectRow' )
			->withConsecutive(
				[ 'user_groups', [ 'ug_user' ], [ 'ug_user' => 1, 'ug_group IN (wiki_Test1_reader,wiki__global_reader)' ] ],
				[ 'user_groups', [ 'ug_user' ], [ 'ug_user' => 1, 'ug_group IN (wiki_Test2_reader,wiki__global_reader)' ] ]
			)
			->willReturn( (object)[ 'ug_user' => 1 ] );

		$managementDBFactoryMock = $this->createMock( ManagementDatabaseFactory::class );
		$managementDBFactoryMock->method( 'createSharedUserDatabaseConnection' )->willReturn( $dbMock );

		$accessStore = new AccessStore( $managementDBFactoryMock, $creator, $this->createMock( TeamQuery::class ) );
		$accessStore->userHasRoleOnInstance( $this->getUserMock(), 'reader', $this->getInstanceMock( 'Test1' ) );
		$accessStore->userHasRoleOnInstance( $this->getUserMock(), 'reader', $this->getInstanceMock( 'Test2' ) );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\AccessStore::getInstancePathsWhereUserHasRole
	 */
	public function testGetInstancePathsWhereUserHasRole() {
		$storeMock = $this->createMock( DirectInstanceStore::class );
		$storeMock->method( 'getInstancePathsQuick' )->willReturn( [ 'Test1', 'Test2' ] );
		$creator = new InstanceGroupCreator( $storeMock );

		$dbMock = $this->createMock( IDatabase::class );
		$dbMock->expects( $this->once() )
			->method( 'select' )
			->with(
				'user_groups',
				[ 'ug_group' ],
				[ 'ug_user' => 1 ]
			)
			->willReturn( new \Wikimedia\Rdbms\FakeResultWrapper( [
				[ 'ug_group' => 'wiki_Test1_reader' ],
				[ 'ug_group' => 'wiki_Test2_reader' ],
				[ 'ug_group' => 'wiki_Test2_editor' ],
				[ 'ug_group' => 'sysop' ],
			] ) );
		$managementDBFactoryMock = $this->createMock( ManagementDatabaseFactory::class );
		$managementDBFactoryMock->method( 'createSharedUserDatabaseConnection' )->willReturn( $dbMock );

		$accessStore = new AccessStore( $managementDBFactoryMock, $creator, $this->createMock( TeamQuery::class ) );
		$paths = $accessStore->getInstancePathsWhereUserHasRole( $this->getUserMock(), 'reader' );
		$this->assertEquals( [ 'w', 'Test1', 'Test2' ], $paths );
	}

	/**
	 * @return UserIdentity
	 */
	private function getUserMock() {
		$userMock = $this->createMock( UserIdentity::class );
		$userMock->method( 'getId' )->willReturn( 1 );
		return $userMock;
	}

	/**
	 * @param string $path
	 * @return InstanceEntity
	 */
	private function getInstanceMock( $path ) {
		$instanceMock = $this->createMock( InstanceEntity::class );
		$instanceMock->method( 'getPath' )->willReturn( $path );
		return $instanceMock;
	}

}
