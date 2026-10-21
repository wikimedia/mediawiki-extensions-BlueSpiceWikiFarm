<?php

namespace BlueSpice\WikiFarm\Export\Step;

use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHelper;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;

class Cleanup extends ExportStep implements InterruptingProcessStep {
	private const MAX_TRANSACTION_STEPS = 900;

	/**
	 * @return void
	 */
	protected function runStep(): void {
		$tempBackend = $this->storage->getTempBackend();
		$storageHelper = new StorageHelper( $tempBackend );
		$files = $tempBackend->getFileList( [
			'dir' => $storageHelper->compileZonePath( $this->getExportDirectory() ),
			'topOnly' => false,
		] );
		$transaction = $this->storage->newTempTransaction();
		$steps = 0;

		foreach ( $files as $file ) {
			$transaction->delete( $file, $this->getExportDirectory() );
			$steps++;
			if ( $steps >= self::MAX_TRANSACTION_STEPS ) {
				$status = $transaction->commit();
				$this->validateStatus( $status );
				$transaction = $this->storage->newTempTransaction();
				$steps = 0;
			}
		}

		if ( $steps > 0 ) {
			$status = $transaction->commit();
			$this->validateStatus( $status );
		}
	}
}
