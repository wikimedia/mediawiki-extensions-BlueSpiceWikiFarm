<?php

namespace BlueSpice\WikiFarm;

use Installer;
use LocalSettingsGenerator;
use MediaWiki\Json\FormatJson;

class InstanceLocalSettingsGenerator extends LocalSettingsGenerator {

	/**
	 * @param Installer $installer
	 */
	public function __construct( Installer $installer ) {
		parent::__construct( $installer );

		/**
		 * Clear all detected skins and extensions, as in BlueSpice such things
		 * are completely handeled by `settings.d/`-files
		 */
		$this->skins = [];
		$this->extensions = [];
	}

	/** @inheritDoc */
	public function writeFile( $fileName ) {
		parent::writeFile( $fileName );

		$path = dirname( $fileName );

		// Add dummy file
		file_put_contents( "$path/LocalSettings.custom.php", "<?php\n\n" );

		// Add meta file
		file_put_contents( "$path/meta.json", $this->makeMetaJSONString() );
	}

	private function makeMetaJSONString() {
		$meta = [
			'desc' => '',
			'group' => '',
			'keywords' => [],
			'cdate' => wfTimestamp( TS_ISO_8601 )
		];

		$jsonString = FormatJson::encode( $meta, true );

		return str_replace( '    ', "\t", $jsonString );
	}

	/**
	 *
	 * @return string
	 */
	public function getDefaultText() {
		// Check if ImageMagick is installed!
		//On Windows there is a `convert` command, but it is not ImageMagick
		exec( "convert -version", $out, $iReturnCode ); // phpcs:ignore MediaWiki.Usage.ForbiddenFunctions.exec
		if ( $iReturnCode != 0 ) {
			$this->values['wgImageMagickConvertCommand'] = false;
		}

		$this->values['wgEnableUploads'] = true;

		return parent::getDefaultText();
	}
}
