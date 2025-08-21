<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\MainConfigNames;
use MediaWikiIntegrationTestCase;
use Psr\Log\NullLogger;
use RuntimeException;

/**
 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager
 * @group Database
 */
class TeamManagerTest extends MediaWikiIntegrationTestCase {

	/** @var TeamManager|null */
	private ?TeamManager $manager = null;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		$this->overrideConfigValues( [
			MainConfigNames::SharedDB => null,
			MainConfigNames::SharedTables => [],
		] );
		$this->setMwGlobals( [
			'wgAdditionalGroups' => [ 'team-Test' => [] ]
		] );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::createTeam
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getTeam
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getAllTeams
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::deleteTeam
	 * @return void
	 */
	public function testTeamHandling() {
		$manager = $this->getManager();

		// Test creation
		$manager->createTeam( 'Test', 'Test description', $this->getTestSysop()->getAuthority() );
		$team = $manager->getTeam( 'Test' );
		$this->assertSame( 'Test', $team->getName() );
		$this->assertSame( 'Test description', $team->getDescription() );
		$this->assertCount( 1, $manager->getAllTeams() );

		// Test duplicate creation
		$this->expectException( RuntimeException::class );
		$manager->createTeam( 'Test', 'Test description', $this->getTestSysop()->getAuthority() );

		// Test deletion
		$manager->deleteTeam( $team, $this->getTestSysop()->getAuthority() );
		$this->assertSame( [], $manager->getAllTeams() );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::assignUserToTeam
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getMembers
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getMemberCount
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::removeUserFromTeam
	 * @return void
	 */
	public function testUserAssignmentsToTeam() {
		$manager = $this->getManager();
		$manager->createTeam( 'Test', 'Test description', $this->getTestSysop()->getAuthority() );
		$team = $manager->getTeam( 'Test' );

		// Test assignments
		$manager->assignUserToTeam( $this->getTestUser()->getUser(), $team, $this->getTestSysop()->getAuthority() );
		$this->assertSame( 1, $manager->getMemberCount( $team ) );
		$members = $manager->getMembers( $team );
		$this->assertArrayHasKey( $this->getTestUser()->getUser()->getName(), $members );

		// Test un-assignments
		$manager->removeUserFromTeam( $this->getTestUser()->getUser(), $team, $this->getTestSysop()->getAuthority() );
		$this->assertSame( 0, $manager->getMemberCount( $team ) );
		$members = $manager->getMembers( $team );
		$this->assertSame( [], $members );

		// Test auto-unassignment after deletion
		$manager->assignUserToTeam( $this->getTestUser()->getUser(), $team, $this->getTestSysop()->getAuthority() );
		$groups = $this->getServiceContainer()->getUserGroupManager()->getUserGroups( $this->getTestUser()->getUser() );
		$this->assertContains( 'team-Test', $groups );
		$manager->deleteTeam( $team, $this->getTestSysop()->getAuthority() );
		$groups = $this->getServiceContainer()->getUserGroupManager()->getUserGroups( $this->getTestUser()->getUser() );
		$this->assertNotContains( 'team-Test', $groups );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::assignRoleToTeam
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getTeamRoles
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::removeAllRoles
	 * @covers \BlueSpice\WikiFarm\AccessControl\TeamManager::getUserRolesForInstance
	 * @return void
	 */
	public function testRoleAssignment() {
		$manager = $this->getManager();
		$manager->createTeam( 'Test', 'Test description', $this->getTestSysop()->getAuthority() );
		$team = $manager->getTeam( 'Test' );
		$instance = new RootInstanceEntity();

		// Assign role
		$manager->assignRoleToTeam( 'reader', $team, $instance, $this->getTestSysop()->getAuthority() );
		$allTeamRoles = $manager->getTeamRoles( $instance );
		$this->assertCount( 1, $allTeamRoles );
		$this->assertSame( 'Test', $allTeamRoles[0]['team'] );
		$this->assertSame( 'reader', $allTeamRoles[0]['role'] );

		// Unassign role
		$manager->removeAllRoles( $team, $instance, $this->getTestSysop()->getAuthority() );
		$allTeamRoles = $manager->getTeamRoles( $instance );
		$this->assertCount( 0, $allTeamRoles );

		// User role assignment though team
		$manager->assignUserToTeam( $this->getTestUser()->getUser(), $team, $this->getTestSysop()->getAuthority() );
		$manager->assignRoleToTeam( 'reader', $team, $instance, $this->getTestSysop()->getAuthority() );
		$userRoles = $manager->getUserRolesForInstance( $this->getTestUser()->getUser(), $instance );
		$this->assertSame( [ 'reader' ], $userRoles );

		$manager->assignRoleToTeam( 'maintainer', $team, $instance, $this->getTestSysop()->getAuthority() );
		$userRoles = $manager->getUserRolesForInstance( $this->getTestUser()->getUser(), $instance );
		$this->assertSame( [ 'maintainer' ], $userRoles );
	}

	/**
	 * @return TeamManager
	 */
	private function getManager(): TeamManager {
		if ( !$this->manager ) {
			$this->manager = new TeamManager(
				$this->getDb(),
				$this->getServiceContainer()->getService( 'UserGroupManager' ),
				$this->getServiceContainer()->getService( 'UserFactory' ),
				new NullLogger()
			);
		}

		return $this->manager;
	}
}
