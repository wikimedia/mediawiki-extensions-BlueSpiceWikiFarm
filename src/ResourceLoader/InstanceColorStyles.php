<?php

namespace BlueSpice\WikiFarm\ResourceLoader;

use BlueSpice\WikiFarm\InstanceEntity;
use MediaWiki\MediaWikiServices;
use MediaWiki\ResourceLoader\Context;
use MediaWiki\ResourceLoader\Module as ResourceLoaderModule;
use Throwable;

/**
 * Emits one `.<wikipath>-color` rule per instance of the farm, so that wiki content
 * can be styled with the color of any instance, for example to color-code links or
 * labels that point at another wiki.
 */
class InstanceColorStyles extends ResourceLoaderModule {

	/** Paths are validated against this on creation; anything else cannot be trusted in CSS. */
	private const PATH_PATTERN = '/^[a-zA-Z0-9_\/-]+$/';

	private const COLOR_PATTERN = '/^#[0-9a-fA-F]{3,8}$/';

	private const ROOT_PATH_ALIASES = [ 'w' => 'main' ];

	/**
	 * @inheritDoc
	 */
	public function getStyles( Context $context ) {
		$rules = [];
		foreach ( $this->getInstances() as $instance ) {
			$color = $instance->getMetadata()['instanceColor']['background'] ?? null;
			if ( !is_string( $color ) || !preg_match( self::COLOR_PATTERN, $color ) ) {
				continue;
			}
			foreach ( $this->getClassNames( $instance ) as $className ) {
				$rules[$className] = ".$className { color: $color; }";
			}
		}

		if ( !$rules ) {
			return [];
		}
		return [ 'all' => implode( "\n", $rules ) . "\n" ];
	}

	/**
	 * @inheritDoc
	 */
	public function getType() {
		return self::LOAD_STYLES;
	}

	/**
	 * The rules depend on the farm instance list rather than on any file on disk, so let
	 * ResourceLoader derive the version from the generated content itself.
	 *
	 * @inheritDoc
	 */
	public function enableModuleContentVersion() {
		return true;
	}

	/**
	 * All instances of the farm, including the root instance.
	 *
	 * Reading the map opens a connection to the management database, which only exists when
	 * the request went through the farm dispatcher. Without it - a plain `wfLoadExtension`
	 * setup, as used by CI, where Setup::onRegistration defines the constant as an empty
	 * string - there is nothing to colorize, and letting the failure escape would take down
	 * the whole request. Same guard as InstanceStore::getCurrentInstance().
	 *
	 * @return InstanceEntity[]
	 */
	private function getInstances(): array {
		if ( !defined( 'FARMER_CALLED_INSTANCE' ) || !FARMER_CALLED_INSTANCE ) {
			return [];
		}
		try {
			$wikiMap = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.WikiMap' );
			return array_values( $wikiMap->getMap() );
		} catch ( Throwable $ex ) {
			return [];
		}
	}

	/**
	 * @param InstanceEntity $instance
	 * @return string[] CSS class names, already escaped for use in a selector
	 */
	private function getClassNames( InstanceEntity $instance ): array {
		$path = $instance->getPath();
		if ( !preg_match( self::PATH_PATTERN, $path ) ) {
			return [];
		}
		$paths = [ $path ];
		if ( isset( self::ROOT_PATH_ALIASES[$path] ) ) {
			$paths[] = self::ROOT_PATH_ALIASES[$path];
		}

		return array_map(
			fn ( string $p ): string => $this->escapeIdentifier( $p ) . '-color',
			$paths
		);
	}

	/**
	 * Turn an instance path into something usable after a "." in a selector. Sub-instance
	 * paths contain "/", and a path may start with a digit, neither of which is valid in a
	 * CSS identifier as-is.
	 *
	 * @param string $path Matches self::PATH_PATTERN, so plain ASCII
	 * @return string
	 */
	private function escapeIdentifier( string $path ): string {
		$escaped = preg_replace( '/([^a-zA-Z0-9_-])/', '\\\\$1', $path );
		if ( preg_match( '/^-?[0-9]/', $escaped ) ) {
			// Escape the leading digit by code point, e.g. "1st" => "\31 st"
			$offset = $escaped[0] === '-' ? 1 : 0;
			$escaped = substr( $escaped, 0, $offset )
				. '\\' . dechex( ord( $escaped[$offset] ) ) . ' '
				. substr( $escaped, $offset + 1 );
		}
		return $escaped;
	}
}
