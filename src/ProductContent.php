<?php

namespace BlueSpice\WikiFarm;

/**
 * Resolves product and edition specific default content from the `content/` directory.
 * Keep in sync with `BlueSpice\Installer\BsProductContent` of bluespice/mw-config-overrides.
 *
 * Lookup order for `content/<type>/*.<extension>`:
 * 1. `<product>-<edition>` (e.g. "galaxy-sapphire" or "classic-free")
 * 2. `<product>` (e.g. "galaxy"; holds content that works with every edition of the product)
 * 3. DEFAULT_PRODUCT
 */
class ProductContent {

	public const DEFAULT_PRODUCT = 'classic';

	/**
	 * Values of `BLUESPICE-EDITION` that were used as product name before
	 * `BLUESPICE-PRODUCT` was introduced
	 */
	private const LEGACY_PRODUCT_EDITIONS = [ 'galaxy' ];

	/** @var string|null */
	private static $product = null;

	/** @var string|null */
	private static $edition = null;

	/**
	 * BlueSpice product (e.g. "classic" or "galaxy") from `$IP/BLUESPICE-PRODUCT`
	 *
	 * @return string DEFAULT_PRODUCT if unknown
	 */
	public static function getProduct(): string {
		if ( self::$product === null ) {
			$product = self::readFile( 'BLUESPICE-PRODUCT' );
			if ( $product === '' && in_array( self::getEdition(), self::LEGACY_PRODUCT_EDITIONS ) ) {
				$product = self::getEdition();
			}
			self::$product = $product ?: self::DEFAULT_PRODUCT;
		}

		return self::$product;
	}

	/**
	 * BlueSpice edition (e.g. "free", "pro", "farm" for "classic", or "glacier", "sky",
	 * "sapphire" for "galaxy").
	 * The environment takes precedence over `$IP/BLUESPICE-EDITION`, because one build may
	 * serve multiple editions (e.g. the Galaxy EE build ships "sky" but also runs "sapphire").
	 *
	 * @return string Empty if unknown
	 */
	public static function getEdition(): string {
		if ( self::$edition === null ) {
			self::$edition = '';
			foreach ( [ 'INTERNAL_EDITION', 'EDITION' ] as $envVar ) {
				$edition = self::sanitize( (string)getenv( $envVar ) );
				if ( $edition !== '' ) {
					self::$edition = $edition;
					return self::$edition;
				}
			}
			self::$edition = self::readFile( 'BLUESPICE-EDITION' );
		}

		return self::$edition;
	}

	/**
	 * `content/<type>/<name>.<extension>`, see class description for the lookup order
	 *
	 * @param string $type e.g. "mainpage" or "sidebar"
	 * @param string $extension e.g. "html" or "wikitext"
	 * @return string|null Null if no file exists
	 */
	public static function getPath( string $type, string $extension ): ?string {
		$dir = dirname( __DIR__ ) . "/content/$type";
		foreach ( self::getCandidates() as $name ) {
			$path = "$dir/$name.$extension";
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return null;
	}

	/**
	 * Product a content file belongs to, e.g. "galaxy" for `content/mainpage/galaxy-sky.html`
	 *
	 * @param string $path
	 * @return string
	 */
	public static function getProductFromPath( string $path ): string {
		return explode( '-', pathinfo( $path, PATHINFO_FILENAME ), 2 )[0];
	}

	/**
	 * @return string[]
	 */
	private static function getCandidates(): array {
		$product = self::getProduct();
		$edition = self::getEdition();
		$candidates = [];
		if ( $edition !== '' ) {
			$candidates[] = "$product-$edition";
		}
		$candidates[] = $product;
		$candidates[] = self::DEFAULT_PRODUCT;

		return array_unique( $candidates );
	}

	/**
	 * @param string $fileName
	 * @return string Sanitized first line, empty if not available
	 */
	private static function readFile( string $fileName ): string {
		$path = $GLOBALS['IP'] . '/' . $fileName;
		if ( !file_exists( $path ) ) {
			return '';
		}
		$lines = explode( "\n", (string)file_get_contents( $path ) );

		return self::sanitize( $lines[0] );
	}

	/**
	 * @param string $value
	 * @return string Empty if invalid
	 */
	private static function sanitize( string $value ): string {
		$value = strtolower( trim( $value ) );

		return preg_match( '/^[a-z]+$/', $value ) ? $value : '';
	}
}
