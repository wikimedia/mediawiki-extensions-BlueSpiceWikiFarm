<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Wikimedia\Rdbms\DeleteQueryBuilder;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\InsertQueryBuilder;

if ( !defined( 'MW_ENTRY_POINT' ) ) {
	define( 'MW_ENTRY_POINT', 'test' );
}

/**
 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager
 */
class GroupRoleManagerTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::assignRoleToGroup
	 */
	public function testAssignRoleToGroupThrowsOnInvalidRole() {
		$manager = new TestableGroupRoleManager(
			$this->createMock( IDatabase::class ),
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$this->expectException( RuntimeException::class );
		$manager->assignRoleToGroup(
			'nonexistent-role',
			'alpha',
			$this->createInstanceMock( 'test-wiki', 'inst-1' ),
			$this->createAuthorityMock()
		);
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::assignRoleToGroup
	 */
	public function testAssignRoleToGroupInsertsRow() {
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

		$manager = new TestableGroupRoleManager( $db, $this->createMock( UserFactory::class ), $logger );
		$manager->assignRoleToGroup( 'reader', 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertCount( 1, $manager->logCalls );
		$this->assertSame( 'assign-role', $manager->logCalls[0]['action'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::assignRoleToGroup
	 */
	public function testAssignRoleToGroupUsesInstanceIdInInsert() {
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

		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$manager->assignRoleToGroup( 'editor', 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertSame( [
			'wtr_team' => 'alpha',
			'wtr_instance' => 'inst-99',
			'wtr_role' => 'editor',
		], $insertedRow );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::assignRoleToGroup
	 */
	public function testAssignRoleToGroupWithNullInstanceSetsNullInRow() {
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

		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		// null instance → global assignment
		$manager->assignRoleToGroup( 'reader', 'global-group', null, $this->createAuthorityMock() );

		$this->assertNull( $insertedRow['wtr_instance'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::removeGroupRoles
	 */
	public function testRemoveGroupRolesExecutesDelete() {
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
		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$manager->removeGroupRoles( 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertSame( [ 'wtr_team' => 'alpha', 'wtr_instance' => 'inst-1' ], $deletedWhere );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::removeGroupRoles
	 */
	public function testRemoveGroupRolesLogsWhenFlagIsTrue() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );

		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );
		$manager->removeGroupRoles( 'alpha', $instance, $this->createAuthorityMock(), true );

		$this->assertCount( 1, $manager->logCalls );
		$this->assertSame( 'remove-all-roles', $manager->logCalls[0]['action'] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::removeGroupRoles
	 */
	public function testRemoveGroupRolesDoesNotLogByDefault() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );

		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$instance = $this->createInstanceMock( 'test-wiki', 'inst-1' );
		$manager->removeGroupRoles( 'alpha', $instance, $this->createAuthorityMock() );

		$this->assertCount( 0, $manager->logCalls );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::deleteAllRolesForGroup
	 */
	public function testDeleteAllRolesForGroupExecutesDelete() {
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

		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$manager->deleteAllRolesForGroup( 'alpha', $this->createAuthorityMock() );

		$this->assertSame( [ 'wtr_team' => 'alpha' ], $deletedWhere );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupRoleManager::deleteAllRolesForGroup
	 */
	public function testDeleteAllRolesForGroupLogs() {
		$db = $this->createMock( IDatabase::class );
		$db->method( 'newDeleteQueryBuilder' )->willReturn( $this->createDeleteQueryBuilderMock() );

		$manager = new TestableGroupRoleManager(
			$db,
			$this->createMock( UserFactory::class ),
			$this->createMock( LoggerInterface::class )
		);
		$manager->deleteAllRolesForGroup( 'alpha', $this->createAuthorityMock() );

		$this->assertCount( 1, $manager->logCalls );
		$this->assertSame( 'delete', $manager->logCalls[0]['action'] );
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
