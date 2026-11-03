<?php

namespace BlueSpice\WikiFarm\Tests;

use BlueSpice\WikiFarm\ProductContent;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * @covers \BlueSpice\WikiFarm\ProductContent
 */
class ProductContentTest extends TestCase {

	private const ENV_VARS = [ 'INTERNAL_EDITION', 'EDITION' ];

	/** @var string */
	private $ip;

	/** @var string|null */
	private $originalIP;

	/** @var array<string,string|false> */
	private $originalEnv = [];

	protected function setUp(): void {
		parent::setUp();
		$this->originalIP = $GLOBALS['IP'] ?? null;
		$this->ip = sys_get_temp_dir() . '/ProductContentTest-' . bin2hex( random_bytes( 6 ) );
		mkdir( $this->ip );
		$GLOBALS['IP'] = $this->ip;
		foreach ( self::ENV_VARS as $envVar ) {
			$this->originalEnv[$envVar] = getenv( $envVar );
			putenv( $envVar );
		}
		$this->resetCache();
	}

	protected function tearDown(): void {
		foreach ( [ 'BLUESPICE-PRODUCT', 'BLUESPICE-EDITION' ] as $fileName ) {
			if ( file_exists( "$this->ip/$fileName" ) ) {
				unlink( "$this->ip/$fileName" );
			}
		}
		rmdir( $this->ip );
		$GLOBALS['IP'] = $this->originalIP;
		foreach ( $this->originalEnv as $envVar => $value ) {
			putenv( $value === false ? $envVar : "$envVar=$value" );
		}
		$this->resetCache();
		parent::tearDown();
	}

	/**
	 * @param string|null $productFile Content of `BLUESPICE-PRODUCT`, null for no file
	 * @param string|null $editionFile Content of `BLUESPICE-EDITION`, null for no file
	 * @param array $env
	 * @param string $expectedProduct
	 * @param string $expectedEdition
	 * @covers \BlueSpice\WikiFarm\ProductContent::getProduct
	 * @covers \BlueSpice\WikiFarm\ProductContent::getEdition
	 * @dataProvider provideProductAndEdition
	 */
	public function testProductAndEdition(
		?string $productFile, ?string $editionFile, array $env,
		string $expectedProduct, string $expectedEdition
	) {
		$this->setUpInstallation( $productFile, $editionFile, $env );

		$this->assertSame( $expectedProduct, ProductContent::getProduct() );
		$this->assertSame( $expectedEdition, ProductContent::getEdition() );
	}

	public static function provideProductAndEdition(): array {
		return [
			'nothing set' => [ null, null, [], 'classic', '' ],
			'product only' => [ "galaxy\n", null, [], 'galaxy', '' ],
			'product and edition' => [ "galaxy\n", "sky\n", [], 'galaxy', 'sky' ],
			'values are trimmed and lowercased' => [ "  Galaxy  \n", " SKY\r\n", [], 'galaxy', 'sky' ],
			'only first line is used' => [ "galaxy\nclassic\n", "glacier\nsky", [], 'galaxy', 'glacier' ],
			'invalid product falls back' => [ "../galaxy\n", null, [], 'classic', '' ],
			'invalid edition is ignored' => [ "galaxy\n", "sky-1\n", [], 'galaxy', '' ],
			'empty files' => [ '', '', [], 'classic', '' ],
			'INTERNAL_EDITION wins over file' =>
				[ "galaxy\n", "sky\n", [ 'INTERNAL_EDITION' => 'sapphire' ], 'galaxy', 'sapphire' ],
			'INTERNAL_EDITION wins over EDITION' =>
				[ "galaxy\n", "sky\n", [ 'INTERNAL_EDITION' => 'sapphire', 'EDITION' => 'glacier' ],
					'galaxy', 'sapphire' ],
			'EDITION wins over file' =>
				[ "galaxy\n", "sky\n", [ 'EDITION' => 'glacier' ], 'galaxy', 'glacier' ],
			'invalid env is ignored' =>
				[ "galaxy\n", "sky\n", [ 'INTERNAL_EDITION' => 'sap phire' ], 'galaxy', 'sky' ],
			'legacy: edition file names the product' => [ null, "galaxy\n", [], 'galaxy', 'galaxy' ],
			'legacy only applies without product file' => [ "classic\n", "galaxy\n", [], 'classic', 'galaxy' ],
			'classic edition does not become a product' => [ null, "pro\n", [], 'classic', 'pro' ],
		];
	}

	/**
	 * @param string|null $productFile
	 * @param string|null $editionFile
	 * @param array $env
	 * @param string $expectedMainpage
	 * @param string|null $expectedGettingStarted
	 * @param string $expectedSidebar
	 * @covers \BlueSpice\WikiFarm\ProductContent::getPath
	 * @dataProvider provideGetPath
	 */
	public function testGetPath(
		?string $productFile, ?string $editionFile, array $env,
		string $expectedMainpage, ?string $expectedGettingStarted, string $expectedSidebar
	) {
		$this->setUpInstallation( $productFile, $editionFile, $env );

		$this->assertContentFile( 'mainpage', $expectedMainpage, ProductContent::getPath( 'mainpage', 'html' ) );
		$this->assertContentFile(
			'gettingstarted', $expectedGettingStarted, ProductContent::getPath( 'gettingstarted', 'html' )
		);
		$this->assertContentFile(
			'sidebar', $expectedSidebar, ProductContent::getPath( 'sidebar', 'wikitext' )
		);
	}

	public static function provideGetPath(): array {
		return [
			'no product' => [ null, null, [], 'classic.html', null, 'classic.wikitext' ],
			'classic free' => [ "classic\n", "free\n", [], 'classic.html', null, 'classic.wikitext' ],
			'classic pro' => [ "classic\n", "pro\n", [], 'classic.html', null, 'classic.wikitext' ],
			'classic farm' => [ "classic\n", "farm\n", [], 'classic.html', null, 'classic.wikitext' ],
			'galaxy without edition' =>
				[ "galaxy\n", null, [], 'galaxy.html', 'galaxy.html', 'classic.wikitext' ],
			'galaxy glacier' =>
				[ "galaxy\n", "glacier\n", [], 'galaxy.html', 'galaxy.html', 'classic.wikitext' ],
			'galaxy sky' =>
				[ "galaxy\n", "sky\n", [], 'galaxy-sky.html', 'galaxy-sky.html', 'classic.wikitext' ],
			'galaxy sapphire (env, EE build ships "sky")' =>
				[ "galaxy\n", "sky\n", [ 'INTERNAL_EDITION' => 'sapphire' ],
					'galaxy-sapphire.html', 'galaxy-sapphire.html', 'classic.wikitext' ],
			'galaxy unknown edition' =>
				[ "galaxy\n", "nebula\n", [], 'galaxy.html', 'galaxy.html', 'classic.wikitext' ],
			'unknown product' =>
				[ "andromeda\n", "sky\n", [], 'classic.html', null, 'classic.wikitext' ],
			'legacy galaxy' => [ null, "galaxy\n", [], 'galaxy.html', 'galaxy.html', 'classic.wikitext' ],
		];
	}

	/**
	 * @covers \BlueSpice\WikiFarm\ProductContent::getPath
	 */
	public function testGetPathUnknownType() {
		$this->assertNull( ProductContent::getPath( 'does-not-exist', 'html' ) );
	}

	/**
	 * @covers \BlueSpice\WikiFarm\ProductContent::getProduct
	 * @covers \BlueSpice\WikiFarm\ProductContent::getEdition
	 */
	public function testValuesAreCached() {
		$this->setUpInstallation( "galaxy\n", "sky\n", [] );
		ProductContent::getProduct();
		ProductContent::getEdition();

		file_put_contents( "$this->ip/BLUESPICE-PRODUCT", "classic\n" );
		putenv( 'INTERNAL_EDITION=sapphire' );

		$this->assertSame( 'galaxy', ProductContent::getProduct() );
		$this->assertSame( 'sky', ProductContent::getEdition() );
	}

	/**
	 * @param string $path
	 * @param string $expected
	 * @covers \BlueSpice\WikiFarm\ProductContent::getProductFromPath
	 * @dataProvider provideGetProductFromPath
	 */
	public function testGetProductFromPath( string $path, string $expected ) {
		$this->assertSame( $expected, ProductContent::getProductFromPath( $path ) );
	}

	public static function provideGetProductFromPath(): array {
		return [
			[ '/x/content/gettingstarted/galaxy.html', 'galaxy' ],
			[ '/x/content/gettingstarted/galaxy-sky.html', 'galaxy' ],
			[ '/x/content/gettingstarted/galaxy-sapphire.html', 'galaxy' ],
			[ '/x/content/sidebar/classic.wikitext', 'classic' ],
			[ '/x/content/sidebar/classic-free.wikitext', 'classic' ],
		];
	}

	/**
	 * @param string|null $productFile
	 * @param string|null $editionFile
	 * @param array $env
	 */
	private function setUpInstallation( ?string $productFile, ?string $editionFile, array $env ): void {
		if ( $productFile !== null ) {
			file_put_contents( "$this->ip/BLUESPICE-PRODUCT", $productFile );
		}
		if ( $editionFile !== null ) {
			file_put_contents( "$this->ip/BLUESPICE-EDITION", $editionFile );
		}
		foreach ( $env as $envVar => $value ) {
			putenv( "$envVar=$value" );
		}
	}

	/**
	 * @param string $type
	 * @param string|null $expectedFile
	 * @param string|null $actualPath
	 */
	private function assertContentFile( string $type, ?string $expectedFile, ?string $actualPath ): void {
		if ( $expectedFile === null ) {
			$this->assertNull( $actualPath, "No $type content expected" );
			return;
		}
		$this->assertSame( $this->getContentDir() . "/$type/$expectedFile", $actualPath );
		$this->assertFileExists( $actualPath );
	}

	private function getContentDir(): string {
		return dirname( __DIR__, 2 ) . '/content';
	}

	private function resetCache(): void {
		foreach ( [ 'product', 'edition' ] as $name ) {
			$property = new ReflectionProperty( ProductContent::class, $name );
			$property->setValue( null, null );
		}
	}
}
