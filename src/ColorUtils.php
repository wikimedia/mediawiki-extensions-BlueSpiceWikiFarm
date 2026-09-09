<?php

namespace BlueSpice\WikiFarm;

class ColorUtils {

	/**
	 * Creates a light background color based on wiki color
	 * @param string $hexCode
	 * @param bool $lightText
	 * @return string
	 */
	public function getLightBackground( string $hexCode, bool $lightText ): string {
		$rgb = $this->hexToRgb( $hexCode );
		$hsl = $this->rgbToHsl( $rgb );

		if ( $lightText ) {
			// If darker base tone, reduce saturation and increase lightness
			$hsl['s'] *= 0.55;
			$hsl['l'] += ( 100 - $hsl['l'] ) * 0.9;
		} else {
			// If light base tone, increase saturation and reduce lightness
			// Will keep yellow/orange tone otherwise would be beige
			$hsl['s'] *= 0.8;
			$hsl['l'] += ( 100 - $hsl['l'] ) * 0.8;
		}

		return $this->rgbToHex( $this->hslToRgb( $hsl ) );
	}

	/**
	 * Creates darker shade of wiki color to match color contrast for lightbackground
	 * @param string $hexCode
	 * @param bool $lightText
	 * @return string
	 */
	public function getContrastForeground( string $hexCode, bool $lightText ): string {
		$hexCode = $this->getLightBackground( $hexCode, $lightText );
		$rgb = $this->hexToRgb( $hexCode );
		$hsl = $this->rgbToHsl( $rgb );
		$minimumContrast = 4.5;

		// Start at the current lightness and search towards black.
		$low = 0.0;
		$high = $hsl['l'];
		$bestLightness = $low;

		for ( $i = 0; $i < 20; $i++ ) {
			$lightness = ( $low + $high ) / 2;

			$foregroundRgb = $this->hslToRgb( [
				'h' => $hsl['h'],
				's' => $hsl['s'],
				'l' => $lightness,
			] );

			$contrast = $this->getContrastRatio(
				$rgb,
				$foregroundRgb
			);

			if ( $contrast >= $minimumContrast ) {
				// Contrast is sufficient.
				// Try a lighter foreground to stay as close
				// as possible to the original color.
				$bestLightness = $lightness;
				$low = $lightness;
			} else {
				// Not enough contrast, go darker.
				$high = $lightness;
			}
		}

		return $this->rgbToHex(
			$this->hslToRgb( [
				'h' => $hsl['h'],
				's' => $hsl['s'],
				'l' => $bestLightness,
			] )
		);
	}

	/**
	 * @param string $hexCode
	 * @return array
	 */
	private function hexToRgb( string $hexCode ): array {
		$hexCode = ltrim( $hexCode, '#' );

		if ( strlen( $hexCode ) === 3 ) {
			$hexCode = $hexCode[0] . $hexCode[0]
				. $hexCode[1] . $hexCode[1]
				. $hexCode[2] . $hexCode[2];
		}

		return [
			hexdec( substr( $hexCode, 0, 2 ) ),
			hexdec( substr( $hexCode, 2, 2 ) ),
			hexdec( substr( $hexCode, 4, 2 ) ),
		];
	}

	/**
	 * @param array $rgb
	 * @return string
	 */
	private function rgbToHex( array $rgb ): string {
		return sprintf(
			'#%02X%02X%02X',
			$rgb[0],
			$rgb[1],
			$rgb[2]
		);
	}

	/**
	 * @param array $rgb
	 * @return array
	 */
	private function rgbToHsl( array $rgb ): array {
		$r = $rgb[0] / 255;
		$g = $rgb[1] / 255;
		$b = $rgb[2] / 255;

		$max = max( $r, $g, $b );
		$min = min( $r, $g, $b );
		$delta = $max - $min;

		$l = ( $max + $min ) / 2;

		if ( $delta === 0.0 ) {
			return [
				'h' => 0,
				's' => 0,
				'l' => $l * 100,
			];
		}

		$s = $l > 0.5
			? $delta / ( 2 - $max - $min )
			: $delta / ( $max + $min );

		switch ( $max ) {
			case $r:
				$h = ( $g - $b ) / $delta + ( $g < $b ? 6 : 0 );
				break;

			case $g:
				$h = ( $b - $r ) / $delta + 2;
				break;

			default:
				$h = ( $r - $g ) / $delta + 4;
				break;
		}

		$h *= 60;

		return [
			'h' => $h,
			's' => $s * 100,
			'l' => $l * 100,
		];
	}

	/**
	 * @param array $hsl
	 * @return array
	 */
	private function hslToRgb( array $hsl ): array {
		$h = $hsl['h'] / 360;
		$s = $hsl['s'] / 100;
		$l = $hsl['l'] / 100;

		if ( $s === 0.0 ) {
			$value = (int)round( $l * 255 );

			return [ $value, $value, $value ];
		}

		$q = $l < 0.5
			? $l * ( 1 + $s )
			: $l + $s - ( $l * $s );

		$p = 2 * $l - $q;

		return [
			(int)round( $this->hueToRgb( $p, $q, $h + 1 / 3 ) * 255 ),
			(int)round( $this->hueToRgb( $p, $q, $h ) * 255 ),
			(int)round( $this->hueToRgb( $p, $q, $h - 1 / 3 ) * 255 ),
		];
	}

	/**
	 * @param float $p
	 * @param float $q
	 * @param float $t
	 * @return float
	 */
	private function hueToRgb(
		float $p,
		float $q,
		float $t
	): float {
		if ( $t < 0 ) {
			$t += 1;
		}

		if ( $t > 1 ) {
			$t -= 1;
		}

		if ( $t < 1 / 6 ) {
			return $p + ( $q - $p ) * 6 * $t;
		}

		if ( $t < 1 / 2 ) {
			return $q;
		}

		if ( $t < 2 / 3 ) {
			return $p + ( $q - $p ) * ( 2 / 3 - $t ) * 6;
		}

		return $p;
	}

	/**
	 * @param array $rgb1
	 * @param array $rgb2
	 * @return float
	 */
	private function getContrastRatio( array $rgb1, array $rgb2 ): float {
		$luminance1 = $this->getRelativeLuminance( $rgb1 );
		$luminance2 = $this->getRelativeLuminance( $rgb2 );

		$lighter = max( $luminance1, $luminance2 );
		$darker = min( $luminance1, $luminance2 );

		return ( $lighter + 0.05 ) / ( $darker + 0.05 );
	}

	/**
	 * @param array $rgb
	 * @return float
	 */
	private function getRelativeLuminance( array $rgb ): float {
		$channels = array_map(
			static function ( int $channel ): float {
				$value = $channel / 255;

				return $value <= 0.04045
					? $value / 12.92
					: pow( ( $value + 0.055 ) / 1.055, 2.4 );
			},
			$rgb
		);

		return 0.2126 * $channels[0]
			+ 0.7152 * $channels[1]
			+ 0.0722 * $channels[2];
	}
}
