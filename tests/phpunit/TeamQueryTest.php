<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\TeamQuery;
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
 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery
 */
class TeamQueryTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getTeamPrefix
	 */
	public function testGetTeamPrefix() {
		$teamQuery = new TeamQuery( $this->createDbMock() );
		$this->assertSame( 'team-', $teamQuery->getTeamPrefix() );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getTeamGroupName
	 */
	public function testGetTeamGroupName() {
		$teamQuery = new TeamQuery( $this->createDbMock() );
		$this->assertSame( 'team-developers', $teamQuery->getTeamGroupName( 'developers' ) );
		$this->assertSame( 'team-', $teamQuery->getTeamGroupName( '' ) );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getAllTeamGroups
	 */
	public function testGetAllTeamGroupsReturnsEmptyArrayWhenNoRows() {
		$db = $this->createDbMock( new FakeResultWrapper( [] ) );
		$teamQuery = new TeamQuery( $db );
		$this->assertSame( [], $teamQuery->getAllTeamGroups() );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getAllTeamGroups
	 */
	public function testGetAllTeamGroupsReturnsDistinctGroups() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'group' => 'team-alpha' ],
			[ 'group' => 'team-beta' ],
		] ) );
		$teamQuery = new TeamQuery( $db );
		$result = $teamQuery->getAllTeamGroups();
		$this->assertSame( [ 'team-alpha', 'team-beta' ], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceReturnsEmptyForUnregisteredUser() {
		$teamQuery = new TeamQuery( $this->createDbMock() );
		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )->willReturn( false );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $teamQuery->getUserRolesForInstance( $user, $instance );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceFiltersInvalidRoles() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'ug_group' => 'team-alpha', 'wtr_role' => 'invalid-role' ],
		] ) );
		$teamQuery = new TeamQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$result = $teamQuery->getUserRolesForInstance( $user, $instance );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceReturnsValidRoles() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'ug_group' => 'team-alpha', 'wtr_role' => 'reader' ],
			[ 'ug_group' => 'team-alpha', 'wtr_role' => 'editor' ],
			[ 'ug_group' => 'team-alpha', 'wtr_role' => 'invalid-role' ],
		] ) );
		$teamQuery = new TeamQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$result = $teamQuery->getUserRolesForInstance( $user, $instance );
		$this->assertContains( 'reader', $result );
		$this->assertContains( 'editor', $result );
		$this->assertNotContains( 'invalid-role', $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceDeduplicatesRoles() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'ug_group' => 'team-alpha', 'wtr_role' => 'reader' ],
			[ 'ug_group' => 'team-beta', 'wtr_role' => 'reader' ],
		] ) );
		$teamQuery = new TeamQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$result = $teamQuery->getUserRolesForInstance( $user, $instance );
		$this->assertSame( [ 'reader' ], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getUserRolesForInstance
	 */
	public function testGetUserRolesForInstanceCachesResult() {
		$qbMock = $this->createSelectQueryBuilderMock(
			new FakeResultWrapper( [
				[ 'ug_group' => 'team-alpha', 'wtr_role' => 'reader' ],
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

		$teamQuery = new TeamQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$teamQuery->getUserRolesForInstance( $user, $instance );
		$teamQuery->getUserRolesForInstance( $user, $instance );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getUserRolesForInstance
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::clearCache
	 */
	public function testClearCacheInvalidatesCache() {
		$qbMock = $this->createSelectQueryBuilderMock(
			new FakeResultWrapper( [
				[ 'ug_group' => 'team-alpha', 'wtr_role' => 'reader' ],
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

		$teamQuery = new TeamQuery( $db );
		$user = $this->createRegisteredUserMock( 42, 'TestUser' );
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$teamQuery->getUserRolesForInstance( $user, $instance );
		$teamQuery->clearCache( $user, $instance );
		$teamQuery->getUserRolesForInstance( $user, $instance );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getTeamRoles
	 */
	public function testGetTeamRolesReturnsRolesForInstance() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'wtr_team' => 'alpha', 'wtr_role' => 'reader', 'wtr_instance' => 'test-wiki' ],
			[ 'wtr_team' => 'beta', 'wtr_role' => 'editor', 'wtr_instance' => null ],
		] ) );
		$teamQuery = new TeamQuery( $db );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $teamQuery->getTeamRoles( $instance );
		$this->assertCount( 2, $result );
		$this->assertSame( [ 'team' => 'alpha', 'role' => 'reader', 'isGlobal' => false ], $result[0] );
		$this->assertSame( [ 'team' => 'beta', 'role' => 'editor', 'isGlobal' => true ], $result[1] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getTeamRoles
	 */
	public function testGetTeamRolesSkipsLegacyNumericTeamIds() {
		$db = $this->createDbMock( new FakeResultWrapper( [
			[ 'wtr_team' => '42', 'wtr_role' => 'reader', 'wtr_instance' => 'test-wiki' ],
			[ 'wtr_team' => 'alpha', 'wtr_role' => 'editor', 'wtr_instance' => 'test-wiki' ],
		] ) );
		$teamQuery = new TeamQuery( $db );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $teamQuery->getTeamRoles( $instance );
		$this->assertCount( 1, $result );
		$this->assertSame( 'alpha', $result[0]['team'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamQuery::getTeamRoles
	 */
	public function testGetTeamRolesReturnsEmptyArrayWhenNoRows() {
		$db = $this->createDbMock( new FakeResultWrapper( [] ) );
		$teamQuery = new TeamQuery( $db );
		$instance = $this->createInstanceMock( 'test-wiki' );

		$result = $teamQuery->getTeamRoles( $instance );
		$this->assertSame( [], $result );
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
