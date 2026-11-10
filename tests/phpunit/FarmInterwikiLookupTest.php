<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\FarmInterwikiLookup;
use BlueSpice\WikiFarm\Hook\HandleInterwiki;
use MediaWiki\Config\HashConfig;
use MediaWiki\Interwiki\InterwikiLookup;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\WikiFarm\FarmInterwikiLookup
 */
class FarmInterwikiLookupTest extends TestCase {

	public function testGetAllPrefixesIncludesFarmLinksAndRespectsLocalFilter(): void {
		$interwikiLookup = $this->createMock( InterwikiLookup::class );
		$databasePrefixes = [
			[
				'iw_prefix' => 'external',
				'iw_url' => 'https://external.example/$1',
				'iw_local' => 0,
			],
			[
				'iw_prefix' => 'local',
				'iw_url' => 'https://local.example/$1',
				'iw_local' => 1,
			],
		];
		$interwikiLookup->method( 'getAllPrefixes' )->willReturnCallback(
			static function ( $local ) use ( $databasePrefixes ): array {
				if ( $local === null ) {
					return $databasePrefixes;
				}

				return array_values( array_filter(
					$databasePrefixes,
					static fn ( array $link ): bool => (bool)$link['iw_local'] === $local
				) );
			}
		);
		$farmConfig = new HashConfig( [
			'interwikiLinks' => [
				'wiki-target' => [
					'iw_prefix' => 'wiki-target',
					'iw_url' => 'https://farm.example/target/wiki/$1',
					'iw_local' => false,
				],
			],
		] );
		$lookup = new FarmInterwikiLookup( $interwikiLookup, $farmConfig );

		$all = $lookup->getAllPrefixes();
		$this->assertSame(
			[ 'wiki-target', 'external', 'local' ],
			array_column( $all, 'iw_prefix' )
		);
		$this->assertSame(
			[ 'wiki-target', 'external' ],
			array_column( $lookup->getAllPrefixes( false ), 'iw_prefix' )
		);
		$this->assertSame(
			[ 'local' ],
			array_column( $lookup->getAllPrefixes( true ), 'iw_prefix' )
		);
	}

	public function testFarmLinkOverridesDatabaseEntryWithSamePrefix(): void {
		$interwikiLookup = $this->createMock( InterwikiLookup::class );
		$interwikiLookup->method( 'getAllPrefixes' )->willReturn( [
			[
				'iw_prefix' => 'wiki-target',
				'iw_url' => 'https://stale.example/$1',
				'iw_local' => 0,
			],
		] );
		$farmLink = [
			'iw_prefix' => 'wiki-target',
			'iw_url' => 'https://farm.example/target/wiki/$1',
			'iw_local' => false,
		];
		$lookup = new FarmInterwikiLookup(
			$interwikiLookup,
			new HashConfig( [ 'interwikiLinks' => [ 'wiki-target' => $farmLink ] ] )
		);

		$this->assertSame( [ $farmLink ], $lookup->getAllPrefixes() );
	}

	public function testInterwikiStoreDoesNotDuplicateFarmLinksAndDisablesEditing(): void {
		$configuredLink = [
			'iw_prefix' => 'wiki-target',
			'iw_url' => 'https://farm.example/target/wiki/$1',
			'iw_local' => false,
		];
		$missingLink = [
			'iw_prefix' => 'wiki-other',
			'iw_url' => 'https://farm.example/other/wiki/$1',
			'iw_local' => false,
		];
		$handler = new HandleInterwiki( new HashConfig( [
			'interwikiLinks' => [
				'wiki-target' => $configuredLink,
				'wiki-other' => $missingLink,
			],
		] ) );
		$data = [
			(object)[
				'iw_prefix' => 'wiki-target',
				'iw_url' => 'https://stale.example/$1',
			],
			(object)[
				'iw_prefix' => 'external',
				'iw_url' => 'https://external.example/$1',
			],
		];

		$handler->onBSApiInterwikiStoreMakeData( null, $data );

		$this->assertSame(
			[ 'external', 'wiki-other', 'wiki-target' ],
			array_column( array_map( static fn ( $item ) => (array)$item, $data ), 'iw_prefix' )
		);
		$farmItem = array_values( array_filter(
			$data,
			static fn ( $item ) => $item->iw_prefix === 'wiki-target'
		) )[0];
		$this->assertSame( $configuredLink['iw_url'], $farmItem->iw_url );
		$this->assertFalse( $farmItem->editable );

		$externalItem = array_values( array_filter(
			$data,
			static fn ( $item ) => $item->iw_prefix === 'external'
		) )[0];
		$this->assertTrue( $externalItem->editable );

		$missingFarmItem = array_values( array_filter(
			$data,
			static fn ( $item ) => $item->iw_prefix === 'wiki-other'
		) )[0];
		$this->assertFalse( $missingFarmItem->editable );
	}
}
