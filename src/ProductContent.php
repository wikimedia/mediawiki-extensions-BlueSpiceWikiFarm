<?php

namespace BlueSpice\WikiFarm;

/**
 * Resolves product specific default content from the `content/` directory
 */
class ProductContent {

	public const DEFAULT_PRODUCT = 'classic';

	/** @var string|null */
	private static $product = null;

	/**
	 * BlueSpice product (e.g. "classic" or "galaxy") from `$IP/BLUESPICE-PRODUCT`
	 *
	 * @return string DEFAULT_PRODUCT if unknown
	 */
	public static function getProduct(): string {
		if ( self::$product !== null ) {
			return self::$product;
		}
		self::$product = self::DEFAULT_PRODUCT;
		$path = $GLOBALS['IP'] . '/BLUESPICE-PRODUCT';
		if ( file_exists( $path ) ) {
			$lines = explode( "\n", file_get_contents( $path ) );
			$product = strtolower( trim( $lines[0] ) );
			if ( preg_match( '/^[a-z]+$/', $product ) ) {
				self::$product = $product;
			}
		}

		return self::$product;
	}

	/**
	 * `content/<type>/<product>.<extension>`, falling back to DEFAULT_PRODUCT
	 *
	 * @param string $type e.g. "mainpage" or "sidebar"
	 * @param string $extension e.g. "html" or "wikitext"
	 * @return string|null Null if neither file exists
	 */
	public static function getPath( string $type, string $extension ): ?string {
		$dir = dirname( __DIR__ ) . "/content/$type";
		foreach ( array_unique( [ self::getProduct(), self::DEFAULT_PRODUCT ] ) as $product ) {
			$path = "$dir/$product.$extension";
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		return null;
	}
}
