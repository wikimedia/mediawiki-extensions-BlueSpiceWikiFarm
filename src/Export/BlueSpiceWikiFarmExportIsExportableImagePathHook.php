<?php

namespace BlueSpice\WikiFarm\Export;

interface BlueSpiceWikiFarmExportIsExportableImagePathHook {

	/**
	 * @param string $path
	 * @param string $pathInImages
	 * @param bool &$shouldExport
	 * @return void
	 */
	public function onBlueSpiceWikiFarmExportIsExportableImagePath(
		string $path, string $pathInImages, bool &$shouldExport
	): void;
}
