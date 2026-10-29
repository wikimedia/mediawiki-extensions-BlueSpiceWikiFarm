<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\DirectInstanceStore;
use BlueSpice\WikiFarm\InstancePathGenerator;
use BlueSpice\WikiFarm\Util\SystemInstancesHelper;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BlueSpice\WikiFarm\InstancePathGenerator
 */
class InstancePathGeneratorTest extends TestCase {

	/**
	 * @param string $input
	 * @param string|null $expected
	 * @covers \BlueSpice\WikiFarm\InstancePathGenerator::generateFromName
	 * @covers \BlueSpice\WikiFarm\InstancePathGenerator::checkIfValid
	 * @return void
	 * @throws \Exception
	 * @dataProvider provideGenerateData
	 */
	public function testGenerate( string $input, ?string $expected ) {
		$blacklist = [ 'FB', 'TQBFJOTLD' ];
		$store = $this->createMock( DirectInstanceStore::class );
		$store->method( 'pathAvailable' )->willReturnCallback( static function ( $path ) use ( $blacklist ) {
			return !in_array( $path, $blacklist );
		} );

		if ( $expected === null ) {
			$this->expectException( \Exception::class );
		}

		$systemInstancesHelper = $this->createMock( SystemInstancesHelper::class );
		$systemInstancesHelper->method( 'getSystemInstancePaths' )->willReturn( [] );
		$generator = new InstancePathGenerator( $store, $systemInstancesHelper );
		$generated = $generator->generateFromName( $input );

		if ( is_string( $expected ) ) {
			$this->assertSame( $expected, $generated );
			$this->assertTrue( !in_array( $generated, $blacklist ) );
			$this->assertTrue( $generator->checkIfValid( $generated ) );
		}
	}

	/**
	 * @return array
	 */
	public function provideGenerateData(): array {
		return [
			'valid' => [
				'input' => 'MyWiki',
				'expected' => 'MyWiki',
			],
			'valid-too-long' => [
				'input' => 'MyWikiDummyFooBar',
				'expected' => 'MyWikiDummyFooBar',
			],
			'invalid-char-too-long' => [
				'input' => 'This is a name with invalid characters & is too long',
				'expected' => 'TIANWICITL',
			],
			'valid-with-chars-to-strip' => [
				'input' => 'My & Wiki',
				'expected' => 'MW',
			],
			'blacklisted-cannot-shorten' => [
				'input' => 'FB',
				'expected' => 'FB1',
			],
			'blacklisted' => [
				'input' => 'The quick brown fox jumps over the lazy dog',
				'expected' => 'TG',
			],
			'too-much-whitespace' => [
				'input' => '   Foo   Bar   ',
				'expected' => 'FR',
			],
			'all-invalid' => [
				'input' => '   &&###  ',
				'expected' => null,
			],
			'empty' => [
				'input' => '',
				'expected' => null,
			],
			'trimmable-underscores' => [
				'input' => ' _Foo__',
				'expected' => 'Foo',
			],
		];
	}

	/**
	 * @covers \BlueSpice\WikiFarm\InstancePathGenerator::checkIfValid
	 * @return void
	 */
	public function testSystemInstancePathsAreInvalid(): void {
		$store = $this->createMock( DirectInstanceStore::class );
		$store->method( 'pathAvailable' )->willReturn( true );

		$systemInstancesHelper = $this->createMock( SystemInstancesHelper::class );
		$systemInstancesHelper->method( 'getSystemInstancePaths' )->willReturn( [
			'sharedresources',
			'systemwiki',
		] );

		$generator = new InstancePathGenerator( $store, $systemInstancesHelper );

		$this->assertFalse( $generator->checkIfValid( 'sharedresources' ) );
		$this->assertFalse( $generator->checkIfValid( 'systemwiki' ) );
		$this->assertFalse( $generator->checkIfValid( '__global' ) );

		$this->assertTrue( $generator->checkIfValid( 'sharedresources', userInput: false ) );
	}
}
