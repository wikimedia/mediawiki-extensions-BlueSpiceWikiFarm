<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\GroupRoleQuery;
use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\User\UserIdentity;
use PHPUnit\Framework\TestCase;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

if ( !defined( 'MW_ENTRY_POINT' ) ) {
	define( 'MW_ENTRY_POINT', 'test' );
}

/**
 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery
 */
class GroupRoleQueryTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceReturnsEmptyForUnregisteredUser() {
		$query = new GroupRoleQuery( $this->createDbMock() );
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )->willReturn( false );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $query->getUserRolesForInstance( $user, $instance );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceFiltersInvalidRoles() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'ug_group' => 'group-alpha', 'wtr_role' => 'invalid-role' ],
		] ) );
		$query = new GroupRoleQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$result = $query->getUserRolesForInstance( $user, $instance );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceReturnsValidRoles() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'ug_group' => 'group-alpha', 'wtr_role' => 'reader' ],
			[ 'ug_group' => 'group-alpha', 'wtr_role' => 'editor' ],
			[ 'ug_group' => 'group-alpha', 'wtr_role' => 'invalid-role' ],
		] ) );
		$query = new GroupRoleQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$result = $query->getUserRolesForInstance( $user, $instance );
		$this->assertContains( 'reader', $result );
		$this->assertContains( 'editor', $result );
		$this->assertNotContains( 'invalid-role', $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceDeduplicatesRoles() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'ug_group' => 'group-alpha', 'wtr_role' => 'reader' ],
			[ 'ug_group' => 'group-beta', 'wtr_role' => 'reader' ],
		] ) );
		$query = new GroupRoleQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$result = $query->getUserRolesForInstance( $user, $instance );
		$this->assertSame( [ 'reader' ], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceCachesResult() {
		$qbMock = $this->createSelectQueryBuilderMock(
			new FakeResultWrapper( [
				[ 'ug_group' => 'group-alpha', 'wtr_role' => 'reader' ],
			] )
		);
		$db = $this->getMockBuilder( IDatabase::class )
			->addMethods( [ 'tableExists' ] )
			->getMockForAbstractClass();
		// DB query should only be executed once due to caching
		$db->expects( $this->once() )->method( 'newSelectQueryBuilder' )->willReturn( $qbMock );
		$db->method( 'makeList' )->willReturnCallback( static fn ( $list ) => implode( ' OR ', $list ) );
		$db->method( 'addQuotes' )->willReturnCallback( static fn ( $s ) => "'$s'" );
		$db->method( 'tableExists' )->willReturn( true );

		$query = new GroupRoleQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$query->getUserRolesForInstance( $user, $instance );
		$query->getUserRolesForInstance( $user, $instance );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getUserRolesForInstance
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::clearCache
	 */
	public function testClearCacheInvalidatesCache() {
		$qbMock = $this->createSelectQueryBuilderMock(
			new FakeResultWrapper( [
				[ 'ug_group' => 'group-alpha', 'wtr_role' => 'reader' ],
			] )
		);
		$db = $this->getMockBuilder( IDatabase::class )
			->addMethods( [ 'tableExists' ] )
			->getMockForAbstractClass();
		// After clearCache, DB should be queried again
		$db->expects( $this->exactly( 2 ) )->method( 'newSelectQueryBuilder' )->willReturn( $qbMock );
		$db->method( 'makeList' )->willReturnCallback( static fn ( $list ) => implode( ' OR ', $list ) );
		$db->method( 'addQuotes' )->willReturnCallback( static fn ( $s ) => "'$s'" );
		$db->method( 'tableExists' )->willReturn( true );

		$query = new GroupRoleQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$query->getUserRolesForInstance( $user, $instance );
		$query->clearCache( $user, $instance );
		$query->getUserRolesForInstance( $user, $instance );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getGroupRoles
	 */
	public function testGetGroupRolesReturnsRolesForInstance() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'wtr_team' => 'alpha', 'wtr_role' => 'reader', 'wtr_instance' => 'test-wiki' ],
			[ 'wtr_team' => 'beta', 'wtr_role' => 'editor', 'wtr_instance' => null ],
		] ) );
		$query = new GroupRoleQuery( $db );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $query->getGroupRoles( $instance );
		$this->assertCount( 2, $result );
		$this->assertSame( [ 'group' => 'alpha', 'role' => 'reader', 'isGlobal' => false ], $result[0] );
		$this->assertSame( [ 'group' => 'beta', 'role' => 'editor', 'isGlobal' => true ], $result[1] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getGroupRoles
	 */
	public function testGetGroupRolesReturnsEmptyArrayWhenNoRows() {
		$db = $this->createDbMock( new FakeResultWrapper( [] ) );
		$query = new GroupRoleQuery( $db );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $query->getGroupRoles( $instance );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getAllGroupsWithRoles
	 */
	public function testGetAllGroupsWithRolesReturnsDistinctGroups() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'group_name' => 'alpha' ],
			[ 'group_name' => 'beta' ],
		] ) );
		$query = new GroupRoleQuery( $db );
		$result = $query->getAllGroupsWithRoles();
		$this->assertSame( [ 'alpha', 'beta' ], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleQuery::getAllGroupsWithRoles
	 */
	public function testGetAllGroupsWithRolesReturnsEmptyArrayWhenNoRows() {
		$db = $this->createDbMock( new FakeResultWrapper( [] ) );
		$query = new GroupRoleQuery( $db );
		$this->assertSame( [], $query->getAllGroupsWithRoles() );
	}

	/**
	 * Creates a mock IDatabase that returns the given result for select queries.
	 */
	private function createDbMock( ?FakeResultWrapper $selectResult = null ): IDatabase {
		$qbMock = $this->createSelectQueryBuilderMock( $selectResult ?? new FakeResultWrapper( [] ) );

		$db = $this->getMockBuilder( IDatabase::class )
			->addMethods( [ 'tableExists' ] )
			->getMockForAbstractClass();
		$db->method( 'newSelectQueryBuilder' )->willReturn( $qbMock );
		$db->method( 'makeList' )->willReturnCallback( static fn ( $list ) => implode( ' OR ', $list ) );
		$db->method( 'addQuotes' )->willReturnCallback( static fn ( $s ) => "'$s'" );
		$db->method( 'tableExists' )->willReturn( true );
		return $db;
	}

	private function createSelectQueryBuilderMock( FakeResultWrapper $result ): SelectQueryBuilder {
		$qbMock = $this->getMockBuilder( SelectQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$qbMock->method( 'field' )->willReturnSelf();
		$qbMock->method( 'select' )->willReturnSelf();
		$qbMock->method( 'fields' )->willReturnSelf();
		$qbMock->method( 'from' )->willReturnSelf();
		$qbMock->method( 'join' )->willReturnSelf();
		$qbMock->method( 'conds' )->willReturnSelf();
		$qbMock->method( 'where' )->willReturnSelf();
		$qbMock->method( 'caller' )->willReturnSelf();
		$qbMock->method( 'fetchResultSet' )->willReturn( $result );
		return $qbMock;
	}

	private function createRegisteredUserMock( int $id, string $name ): UserIdentity {
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )->willReturn( true );
		$user->method( 'getId' )->willReturn( $id );
		$user->method( 'getName' )->willReturn( $name );
		return $user;
	}

	private function createInstanceMock( string $path, string $id = 'inst-default' ): InstanceEntity {
		$instance = $this->createMock( InstanceEntity::class );
		$instance->method( 'getPath' )->willReturn( $path );
		$instance->method( 'getId' )->willReturn( $id );
		return $instance;
	}
}
