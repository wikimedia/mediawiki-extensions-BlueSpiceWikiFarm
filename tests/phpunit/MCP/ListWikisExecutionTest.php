<?php

namespace BlueSpice\WikiFarm\Tests\MCP;

use BlueSpice\WikiFarm\AccessControl\IAccessStore;
use BlueSpice\WikiFarm\FarmWikiMap;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\MCP\ListWikis;
use BlueSpice\WikiFarm\MCP\ListWikisExecution;
use BlueSpice\WikiFarm\RootInstanceEntity;
use MediaWiki\Config\HashConfig;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\WikiFarm\MCP\ListWikisExecution
 */
class ListWikisExecutionTest extends TestCase {

	/**
	 * @param string $class
	 * @param string $name
	 * @param string $path
	 * @param bool $active
	 * @param array $metadata
	 * @return InstanceEntity
	 */
	private function newInstance(
		string $class, string $name, string $path, bool $active = true, array $metadata = []
	): InstanceEntity {
		$instance = $this->createMock( $class );
		$instance->method( 'getDisplayName' )->willReturn( $name );
		$instance->method( 'getPath' )->willReturn( $path );
		$instance->method( 'getUrl' )->willReturn( "https://wiki.example.invalid/$path" );
		$instance->method( 'isActive' )->willReturn( $active );
		$instance->method( 'getMetadata' )->willReturn( $metadata );
		return $instance;
	}

	/**
	 * @param bool $shareUsers
	 * @param string[] $readable wiki IDs the user can read
	 * @return array
	 */
	private function listWikisFor( bool $shareUsers, array $readable ): array {
		$instances = [
			'bluespice' => $this->newInstance( RootInstanceEntity::class, 'Main wiki', 'w' ),
			'sfr_468329' => $this->newInstance(
				InstanceEntity::class, 'Product Development', 'PD', true, [ 'desc' => 'Specs and plans' ]
			),
			'sfr_secret' => $this->newInstance( InstanceEntity::class, 'HR', 'HR' ),
			'sfr_suspended' => $this->newInstance( InstanceEntity::class, 'Old', 'Old', false ),
		];
		$wikiMap = $this->createMock( FarmWikiMap::class );
		$wikiMap->method( 'getMap' )->willReturn( $instances );

		$accessStore = $this->createMock( IAccessStore::class );
		$accessStore->method( 'userHasRoleOnInstance' )->willReturnCallback(
			static function ( UserIdentity $user, string $role, InstanceEntity $instance ) use ( $instances, $readable ) {
				return $role === 'reader' && in_array( array_search( $instance, $instances, true ), $readable, true );
			}
		);

		$execution = $this->getMockBuilder( ListWikisExecution::class )
			->setConstructorArgs( [ $wikiMap, $accessStore, new HashConfig( [ 'shareUsers' => $shareUsers ] ) ] )
			->onlyMethods( [ 'getUser', 'getCurrentWikiId' ] )
			->getMock();
		$execution->method( 'getUser' )->willReturn( new UserIdentityValue( 5, 'TestUser' ) );
		$execution->method( 'getCurrentWikiId' )->willReturn( 'bluespice' );

		return $execution->execute( new ListWikis(), [] )['wikis'];
	}

	public function testListsOnlyWikisTheUserCanRead() {
		$wikis = $this->listWikisFor( true, [ 'bluespice', 'sfr_468329' ] );

		$this->assertSame( [ 'bluespice', 'sfr_468329' ], array_column( $wikis, 'wiki_id' ) );
	}

	public function testReturnsSitenameDescriptionPathAndUrl() {
		$wikis = $this->listWikisFor( true, [ 'sfr_468329' ] );

		$this->assertSame( [ [
			'wiki_id' => 'sfr_468329',
			'sitename' => 'Product Development',
			'description' => 'Specs and plans',
			'path' => 'PD',
			'url' => 'https://wiki.example.invalid/PD',
			'is_main' => false
		] ], $wikis );
	}

	public function testMarksTheMainWikiAndUsesAnEmptyDescriptionWhenNoneIsSet() {
		$wikis = $this->listWikisFor( true, [ 'bluespice' ] );

		$this->assertTrue( $wikis[0]['is_main'] );
		$this->assertSame( '', $wikis[0]['description'] );
	}

	public function testSkipsInactiveWikisEvenIfReadable() {
		$wikis = $this->listWikisFor( true, [ 'sfr_suspended' ] );

		$this->assertSame( [], $wikis );
	}

	public function testUserWithoutAccessGetsAnEmptyList() {
		$this->assertSame( [], $this->listWikisFor( true, [] ) );
	}

	public function testWithoutSharedUsersOnlyTheCurrentWikiIsListed() {
		$wikis = $this->listWikisFor( false, [ 'bluespice', 'sfr_468329' ] );

		$this->assertSame( [ 'bluespice' ], array_column( $wikis, 'wiki_id' ) );
	}
}
