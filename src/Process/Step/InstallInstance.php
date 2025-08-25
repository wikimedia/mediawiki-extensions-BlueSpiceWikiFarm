<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MediaWiki\Config\Config;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Message\Message;
use Throwable;

class InstallInstance extends RunMaintenanceScript {

	/** @var string */
	protected $langCode;

	/**
	 * @param InstanceManager $instanceManager
	 * @param Config $mainConfig
	 * @param LanguageFactory $langFactory
	 * @param string $instanceId
	 * @param string $langCode
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, Config $mainConfig, LanguageFactory $langFactory,
		string $instanceId, string $langCode
	) {
		parent::__construct( $instanceManager, $mainConfig, $instanceId );
		$this->langCode = $this->isValidLangCode( $langCode, $langFactory ) ?
			$langCode :
			$mainConfig->get( 'LanguageCode' );
	}

	/**
	 * @param array $previousStepData
	 *
	 * @return array
	 */
	protected function getFormattedArgs( array $previousStepData ): array {
		return [
			'--instanceDisplayName', $this->getInstance()->getDisplayName(),
			'--scriptpath', $this->getInstance()->getScriptPath( $this->instanceManager->getFarmConfig() ),
			'--dbname', $this->getInstance()->getDBName(),
			'--dbprefix', $this->getInstance()->getDBPrefix(),
			'--lang', $this->langCode,
			'--dbuser', $this->getInstanceManager()->getFarmConfig()->get( 'dbAdminUser' ),
			'--dbpass', $this->getInstanceManager()->getFarmConfig()->get( 'dbAdminPassword' ),
			'--dbserver', $this->mainConfig->get( 'DBserver' ),
			'--server', $this->mainConfig->get( 'Server' ),
		];
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $data = [] ): array {
		if ( $this->getInstance()->getStatus() !== InstanceEntity::STATUS_INIT ) {
			$this->getInstanceManager()->getLogger()->error( 'Instance in wrong state for installation: {path} => {state}', [
				'path' => $this->getInstance()->getPath(),
				'state' => $this->getInstance()->getStatus()
			] );
			throw new Exception( Message::newFromKey( 'wikifarm-error-unknown' )->text() );
		}
		$res = parent::execute( $data );
		$this->getInstance()->setStatus( InstanceEntity::STATUS_INSTALLED );
		$this->getInstanceManager()->getStore()->store( $this->getInstance() );
		return $res;
	}

	/**
	 * @return string
	 */
	protected function getScriptPath(): string {
		return 'InstallInstance.php';
	}

	/**
	 * @param string $code
	 * @param LanguageFactory $languageFactory
	 * @return bool
	 */
	private function isValidLangCode( string $code, LanguageFactory $languageFactory ): bool {
		try {
			$languageFactory->getLanguage( $code );
			return true;
		} catch ( Throwable $exception ) {
			return false;
		}
	}
}
