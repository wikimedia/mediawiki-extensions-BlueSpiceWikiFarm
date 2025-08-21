<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\DirectInstanceStore;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\WikiFarm\AccessControl\GroupCreator
 */
class GroupCreatorTest extends TestCase {

	/**
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupCreator::getAllGroupsAndRoles
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupCreator::getGroupsAndRolesForInstancePath
	 * @covers \BlueSpice\WikiFarm\AccessControl\GroupCreator::getGroupNameForUserRole
	 */
	public function testGetAllGroupsAndRoles() {
		$storeMock = $this->createMock( DirectInstanceStore::class );
		$storeMock->method( 'getInstancePathsQuick' )->willReturn( [ 'Test1', 'Test2' ] );

		$creator = new \BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator( $storeMock );
		$groups = $creator->getInstanceGroups();

		$this->assertEquals( [
			'Test1' => [
				'wiki_Test1_reader' => [ 'reader' ],
				'wiki_Test1_editor' => [ 'reader', 'editor' ],
				'wiki_Test1_reviewer' => [ 'reader', 'editor', 'reviewer' ],
				'wiki_Test1_maintainer' => [ 'reader', 'editor', 'reviewer', 'admin' ],
			],
			'Test2' => [
				'wiki_Test2_reader' => [ 'reader' ],
				'wiki_Test2_editor' => [ 'reader', 'editor' ],
				'wiki_Test2_reviewer' => [ 'reader', 'editor', 'reviewer' ],
				'wiki_Test2_maintainer' => [ 'reader', 'editor', 'reviewer', 'admin' ],
			],
			'w' => [
				'wiki_w_reader' => [ 'reader' ],
				'wiki_w_editor' => [ 'reader', 'editor' ],
				'wiki_w_reviewer' => [ 'reader', 'editor', 'reviewer' ],
				'wiki_w_maintainer' => [ 'reader', 'editor', 'reviewer', 'admin' ],
			],
			'_global' => [
				'wiki__global_reader' => [ 'reader' ],
				'wiki__global_editor' => [ 'reader', 'editor' ],
				'wiki__global_reviewer' => [ 'reader', 'editor', 'reviewer' ],
				'wiki__global_maintainer' => [ 'reader', 'editor', 'reviewer', 'admin' ],
			]
		], $groups );
	}
}
