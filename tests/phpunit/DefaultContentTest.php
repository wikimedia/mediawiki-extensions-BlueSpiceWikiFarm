<?php

namespace BlueSpice\WikiFarm\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integrity of the default content in `content/`, which is resolved by `ProductContent`
 *
 * @coversNothing
 */
class DefaultContentTest extends TestCase {

	private const MESSAGE_PREFIX = 'wikifarm-';

	/**
	 * Features that are only available in some editions: content type => [
	 *   message key (without prefix) that must only be used by the listed variants => variants
	 * ]
	 */
	private const EDITION_FEATURES = [
		'mainpage' => [
			'gettingstarted-content-galaxy-spreadsheet-heading' => [ 'galaxy-sapphire' ],
			'gettingstarted-content-galaxy-minutes-heading' => [ 'galaxy-sky', 'galaxy-sapphire' ],
			'gettingstarted-content-galaxy-controlleddocument-heading' => [ 'galaxy-sky', 'galaxy-sapphire' ],
		],
		'gettingstarted' => [
			'gettingstarted-content-galaxy-spreadsheet-heading' => [ 'galaxy-sapphire' ],
			'gettingstarted-content-galaxy-minutes-heading' => [ 'galaxy-sky', 'galaxy-sapphire' ],
			'gettingstarted-content-galaxy-controlleddocument-heading' => [ 'galaxy-sky', 'galaxy-sapphire' ],
			// Single sign-on extensions are not part of Galaxy CE ("Glacier")
			'gettingstarted-content-galaxy-sso-heading' => [ 'galaxy-sky', 'galaxy-sapphire' ],
			// "Shared resources" wiki is disabled in Galaxy CE ("Glacier")
			'gettingstarted-content-galaxy-logo-body' => [ 'galaxy-sky', 'galaxy-sapphire' ],
			'gettingstarted-content-galaxy-logo-body-local' => [ 'galaxy' ],
		],
	];

	/**
	 * @param string $path
	 * @dataProvider provideContentFiles
	 */
	public function testReferencedMessagesExist( string $path ) {
		$messages = $this->getMessages( 'en' );
		$documentation = $this->getMessages( 'qqq' );

		$missing = [];
		foreach ( $this->getReferencedMessages( $path ) as $key ) {
			if ( strpos( $key, self::MESSAGE_PREFIX ) !== 0 ) {
				// Messages of other extensions
				continue;
			}
			if ( !isset( $messages[$key] ) ) {
				$missing[] = "$key (en)";
			}
			if ( !isset( $documentation[$key] ) ) {
				$missing[] = "$key (qqq)";
			}
		}
		$this->assertSame( [], $missing, "Messages used in $path are missing" );
	}

	/**
	 * @param string $path
	 * @dataProvider provideGalaxyContentFiles
	 */
	public function testOnlyFeaturesOfEditionAreReferenced( string $path ) {
		$type = basename( dirname( $path ) );
		$variant = pathinfo( $path, PATHINFO_FILENAME );
		$referenced = $this->getReferencedMessages( $path );

		foreach ( self::EDITION_FEATURES[$type] as $key => $variants ) {
			$isReferenced = in_array( self::MESSAGE_PREFIX . $key, $referenced );
			if ( in_array( $variant, $variants ) ) {
				$this->assertTrue( $isReferenced, "'$key' should be referenced in $type/$variant" );
			} else {
				$this->assertFalse( $isReferenced, "'$key' must not be referenced in $type/$variant" );
			}
		}
	}

	/**
	 * Main page links into the "Getting started" page, so both must exist for the same variant
	 */
	public function testGalaxyVariantsAreComplete() {
		$dir = dirname( __DIR__, 2 ) . '/content';
		$mainpages = array_map( 'basename', glob( "$dir/mainpage/galaxy*.html" ) );
		$gettingStarted = array_map( 'basename', glob( "$dir/gettingstarted/galaxy*.html" ) );
		sort( $mainpages );
		sort( $gettingStarted );

		$this->assertContains( 'galaxy.html', $mainpages, 'Fallback for unknown editions is required' );
		$this->assertSame( $mainpages, $gettingStarted );
	}

	public static function provideContentFiles(): array {
		$files = glob( dirname( __DIR__, 2 ) . '/content/*/*.{html,wikitext}', GLOB_BRACE );
		$data = [];
		foreach ( $files as $file ) {
			$data[basename( dirname( $file ) ) . '/' . basename( $file )] = [ $file ];
		}
		return $data;
	}

	public static function provideGalaxyContentFiles(): array {
		$data = [];
		foreach ( static::provideContentFiles() as $name => $args ) {
			if ( preg_match( '#^(mainpage|gettingstarted)/galaxy#', $name ) ) {
				$data[$name] = $args;
			}
		}
		return $data;
	}

	/**
	 * @param string $path
	 * @return string[]
	 */
	private function getReferencedMessages( string $path ): array {
		preg_match_all( '#\{\{int:(.*?)\}\}#si', file_get_contents( $path ), $matches );
		return array_values( array_unique( $matches[1] ) );
	}

	/**
	 * @param string $lang
	 * @return array
	 */
	private function getMessages( string $lang ): array {
		return json_decode(
			file_get_contents( dirname( __DIR__, 2 ) . "/i18n/$lang.json" ), true
		);
	}
}
