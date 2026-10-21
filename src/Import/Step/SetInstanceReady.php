<?php

namespace BlueSpice\WikiFarm\Import\Step;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use JsonException;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Language\Language;
use MWStake\MediaWiki\Component\FileStorageUtilities\StorageHandler;
use MWStake\MediaWiki\Component\ProcessManager\InterruptingProcessStep;

class SetInstanceReady extends ImportStep implements InterruptingProcessStep {

	/**
	 * @param InstanceManager $instanceManager
	 * @param StorageHandler $storage
	 * @param FormatterFactory $formatterFactory
	 * @param Language $language
	 * @param string $loggerName
	 */
	public function __construct(
		private readonly InstanceManager $instanceManager,
		StorageHandler $storage, FormatterFactory $formatterFactory, Language $language,
		string $loggerName
	) {
		parent::__construct( $storage, $formatterFactory, $language, $loggerName );
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	protected function runStep(): void {
		$instance = $this->getInstance( $this->instanceManager );

		if ( $instance->getStatus() === InstanceEntity::STATUS_READY ) {
			return;
		}
		$instance->setStatus( 'ready' );
		$this->instanceManager->getStore()->store( $instance );
		$this->addToData( 'setInstanceReady', true );

		$this->addToData( 'instanceName', $instance->getDisplayName() );
		$this->addToData( 'instanceUrl', $instance->getUrl( $this->instanceManager->getFarmConfig() ) );
	}
}
