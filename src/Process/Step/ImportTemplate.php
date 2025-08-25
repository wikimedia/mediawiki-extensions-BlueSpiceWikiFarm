<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MediaWiki\Config\Config;
use Symfony\Component\Process\Process;

class ImportTemplate extends RunMaintenanceScript {

	/** @var string */
	private string $templateDir;

	/**
	 * @param InstanceManager $instanceManager
	 * @param Config $mainConfig
	 * @param string $instanceId
	 * @param string $templateDir
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, Config $mainConfig, string $instanceId, string $templateDir
	) {
		parent::__construct( $instanceManager, $mainConfig, $instanceId );
		$this->templateDir = $templateDir;
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $data = [] ): array {
		if ( $this->hasPages() ) {
			$this->importPages();
		}
		if ( $this->hasImages() ) {
			$this->importImages();
		}
		if ( $this->hasConfig() ) {
			$this->importConfig();
		}

		return [];
	}

	/**
	 * @return string
	 */
	protected function getScriptPath(): string {
		return '';
	}

	/**
	 * @param array $previousStepData
	 * @return array
	 */
	protected function getFormattedArgs( array $previousStepData ): array {
		return [];
	}

	/**
	 * @return bool
	 */
	protected function hasPages(): bool {
		return file_exists( $this->templateDir . '/pages.xml' );
	}

	/**
	 * @return bool
	 */
	protected function hasImages(): bool {
		$dir = $this->templateDir . '/images';
		// Check if dir exists and is not empty
		return is_dir( $dir ) && count( scandir( $dir ) ) > 2;
	}

	/**
	 * @return bool
	 */
	protected function hasConfig(): bool {
		return file_exists( $this->templateDir . '/config.json' );
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	protected function importPages(): void {
		$process = new Process( [
			$this->getPhpExecutable(), $GLOBALS['IP'] . '/maintenance/importDump.php',
			$this->templateDir . '/pages.xml', '--sfr', $this->instance->getId(),
		] );
		$this->doExecuteProcess( $process );
		if ( !$process->isSuccessful() ) {
			$this->getInstanceManager()->getLogger()->error( 'Import template: Failed to import pages: {error}', [
				'error' => $process->getErrorOutput()
			] );
			throw new Exception( 'Import template: Failed to import pages' );
		}
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	protected function importImages(): void {
		$process = new Process( [
			$this->getPhpExecutable(), $GLOBALS['IP'] . '/maintenance/importImages.php',
			$this->templateDir . '/images', '--sfr', $this->instance->getId(),
		] );
		$this->doExecuteProcess( $process );
		if ( !$process->isSuccessful() ) {
			$this->getInstanceManager()->getLogger()->error( 'Import template: Failed to import images: {error}', [
				'error' => $process->getErrorOutput()
			] );
			throw new Exception( 'Import template: Failed to import images' );
		}
	}

	/**
	 * @return void
	 * @throws Exception
	 */
	protected function importConfig(): void {
		$config = json_decode( file_get_contents( $this->templateDir . '/config.json' ), true );
		if ( !$config ) {
			$this->getInstanceManager()->getLogger()->error( 'Import template: Failed to read config' );
			throw new Exception( 'Import template: Failed to import config' );
		}
		foreach ( $config as $key => $value ) {
			$this->instance->setConfigItem( $key, $value );
		}
		$this->getInstanceManager()->getStore()->store( $this->instance );
	}
}
