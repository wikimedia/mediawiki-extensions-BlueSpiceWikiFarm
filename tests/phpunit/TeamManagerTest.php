<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\Team;
use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\DeleteQueryBuilder;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;
use Wikimedia\Rdbms\SelectQueryBuilder;

if ( !defined( 'MW_ENTRY_POINT' ) ) {
	define( 'MW_ENTRY_POINT', 'test' );
}

/**
 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager
 */
class TeamManagerTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::assignRoleToTeam
	 */
	public function testAssignRoleToTeamThrowsOnInvalidRole() {
		$manager = new TestableTeamManager(
			$this->createMock( IDatabase::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$this->expectException( RuntimeException::class );
		$manager->assignRoleToTeam(
			'nonexistent-role',
			'alpha',
			$this->createInstanceMock( 'test-wiki', 'inst-1' ),
			$this->createAuthorityMock()
		);
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::assignRoleToTeam
	 */
	public function testAssignRoleToTeamInsertsRow() {
		$dqbMock = $this->createDeleteQueryBuilderMock();
		$iqbMock = $this->createInsertQueryBuilderMock();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $dqbMock );
		$db->method( 'newInsertQueryBuilder' )->willReturn( $iqbMock );
		$db->method( 'startAtomic' );
		$db->method( 'endAtomic' );

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->exactly( 2 ) )->method( 'info' );

		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );

		$manager = new TestableTeamManager( $db, $this->createMock( UserFactory::class ), $logger );
		$manager->assignRoleToTeam( 'reader', 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertCount( 1, $manager->logCalls );
		$this->assertSame( 'assign-role', $manager->logCalls[0]['action'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::assignRoleToTeam
	 */
	public function testAssignRoleToTeamUsesInstanceIdInInsert() {
		$insertedRow = null;

		$iqbMock = $this->getMockBuilder( InsertQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$iqbMock->method( 'insert' )->willReturnSelf();
		$iqbMock->method( 'row' )->willReturnCallback( static function ( $row ) use ( &$insertedRow, $iqbMock ) {
			$insertedRow = $row;
			return $iqbMock;
		} );
		$iqbMock->method( 'caller' )->willReturnSelf();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );
		$db->method( 'newInsertQueryBuilder' )->willReturn( $iqbMock );
		$db->method( 'startAtomic' );
		$db->method( 'endAtomic' );

		$instance = $this->createInstanceMock( 'test-wiki', 'inst-99' );

		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$manager->assignRoleToTeam( 'editor', 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertSame( [
			'wtr_team' => 'alpha',
			'wtr_instance' => 'inst-99',
			'wtr_role' => 'editor',
		], $insertedRow );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::assignRoleToTeam
	 */
	public function testAssignRoleToTeamWithNullInstanceSetsNullInRow() {
		$insertedRow = null;

		$iqbMock = $this->getMockBuilder( InsertQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$iqbMock->method( 'insert' )->willReturnSelf();
		$iqbMock->method( 'row' )->willReturnCallback( static function ( $row ) use ( &$insertedRow, $iqbMock ) {
			$insertedRow = $row;
			return $iqbMock;
		} );
		$iqbMock->method( 'caller' )->willReturnSelf();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );
		$db->method( 'newInsertQueryBuilder' )->willReturn( $iqbMock );
		$db->method( 'startAtomic' );
		$db->method( 'endAtomic' );

		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		// null instance → global assignment
		$manager->assignRoleToTeam( 'reader', 'global-team', null, $this->createAuthorityMock() );

		$this->assertNull( $insertedRow['wtr_instance'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::removeAllRoles
	 */
	public function testRemoveAllRolesExecutesDelete() {
		$deletedWhere = null;

		$dqbMock = $this->getMockBuilder( DeleteQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$dqbMock->method( 'delete' )->willReturnSelf();
		$dqbMock->method( 'where' )->willReturnCallback( static function ( $where ) use ( &$deletedWhere, $dqbMock ) {
			$deletedWhere = $where;
			return $dqbMock;
		} );
		$dqbMock->method( 'caller' )->willReturnSelf();

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $dqbMock );

		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );
		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$manager->removeAllRoles( 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertSame( [ 'wtr_team' => 'alpha', 'wtr_instance' => 'inst-1' ], $deletedWhere );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::removeAllRoles
	 */
	public function testRemoveAllRolesLogsWhenFlagIsTrue() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );

		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );
		$manager->removeAllRoles( 'alpha', $instance, $this->createAuthorityMock(), true );

		$this->assertCount( 1, $manager->logCalls );
		$this->assertSame( 'remove-all-roles', $manager->logCalls[0]['action'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::removeAllRoles
	 */
	public function testRemoveAllRolesDoesNotLogByDefault() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );

		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );
		$manager->removeAllRoles( 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertCount( 0, $manager->logCalls );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getMembers
	 */
	public function testGetMembersReturnsEmptyArrayWhenNoRows() {
		$db = $this->createDbMockForMembers( new FakeResultWrapper( [] ) );
		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);

		$team = new Team( 1, 'alpha', 'Alpha team' );
		$result = $manager->getMembers( $team );
		$this->assertSame( [], $result );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getMembers
	 */
	public function testGetMembersReturnsUsersByName() {
		$userRow = (object)[
			'user_name' => 'Alice',
			'user_id' => 1,
			'ug_expiry' => null,
		];
		$db = $this->createDbMockForMembers( new FakeResultWrapper( [ $userRow ] ) );

		$userMock = $this->createMock( User::class );

		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromRow' )->willReturn( $userMock );

		$manager = new TestableTeamManager( $db, $userFactory, $this->createMock( LoggerInterface::class ) );

		$team = new Team( 1, 'alpha', 'Alpha team' );
		$result = $manager->getMembers( $team );

		$this->assertArrayHasKey( 'Alice', $result );
		$this->assertSame( $userMock, $result['Alice']['user'] );
		$this->assertNull( $result['Alice']['expiration'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getMembers
	 */
	public function testGetMembersQueriesTeamGroup() {
		$qbMock = $this->getMockBuilder( SelectQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$qbMock->method( 'from' )->willReturnSelf();
		$qbMock->method( 'join' )->willReturnSelf();
		$qbMock->method( 'select' )->willReturnSelf();
		$qbMock->method( 'caller' )->willReturnSelf();
		// Verify that the correct team group name is used in the where clause
		$qbMock->expects( $this->once() )
			->method( 'where' )
			->with( [ 'ug_group' => 'team-developers' ] )
			->willReturnSelf();
		$qbMock->method( 'fetchResultSet' )->willReturn( new FakeResultWrapper( [] ) );

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newSelectQueryBuilder' )->willReturn( $qbMock );

		$manager = new TestableTeamManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);

		$team = new Team( 1, 'developers', 'Dev team' );
		$manager->getMembers( $team );
	}

	private function createDbMockForMembers( FakeResultWrapper $result ): IDatabase {
		$qbMock = $this->getMockBuilder( SelectQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$qbMock->method( 'from' )->willReturnSelf();
		$qbMock->method( 'join' )->willReturnSelf();
		$qbMock->method( 'select' )->willReturnSelf();
		$qbMock->method( 'where' )->willReturnSelf();
		$qbMock->method( 'caller' )->willReturnSelf();
		$qbMock->method( 'fetchResultSet' )->willReturn( $result );

		$db = $this->createMock( IDatabase::class );
		$db->method( 'newSelectQueryBuilder' )->willReturn( $qbMock );
		return $db;
	}

	private function createDeleteQueryBuilderMock(): DeleteQueryBuilder {
		$mock = $this->getMockBuilder( DeleteQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'delete' )->willReturnSelf();
		$mock->method( 'where' )->willReturnSelf();
		$mock->method( 'caller' )->willReturnSelf();
		return $mock;
	}

	private function createInsertQueryBuilderMock(): InsertQueryBuilder {
		$mock = $this->getMockBuilder( InsertQueryBuilder::class )
			->disableOriginalConstructor()
			->getMock();
		$mock->method( 'insert' )->willReturnSelf();
		$mock->method( 'row' )->willReturnSelf();
		$mock->method( 'caller' )->willReturnSelf();
		return $mock;
	}

	private function createAuthorityMock(): Authority {
		$user = $this->createMock( User::class );
		$authority = $this->createMock( Authority::class );
		$authority->method( 'getUser' )->willReturn( $user );
		return $authority;
	}

	private function createInstanceMock( string $path, string $id = 'inst-default' ): InstanceEntity {
		$instance = $this->createMock( InstanceEntity::class );
		$instance->method( 'getPath' )->willReturn( $path );
		$instance->method( 'getId' )->willReturn( $id );
		return $instance;
	}
}
