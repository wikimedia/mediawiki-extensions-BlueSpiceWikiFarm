<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Process\Step\CreateInstanceVault;
use BlueSpice\WikiFarm\Process\Step\InstallInstance;
use BlueSpice\WikiFarm\Process\Step\PurgeInstance;
use BlueSpice\WikiFarm\Process\Step\RunPostInstanceCreationTasks;
use BlueSpice\WikiFarm\Process\Step\RunUpdates;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\ProcessManager\CodeExecutor;
use Throwable;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class CreateSystemInstances extends \MediaWiki\Maintenance\LoggedUpdateMaintenance {

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doDBUpdates() {
		if (
			$GLOBALS['wgWikiFarmConfigInternal']->get( 'shareUserSessions' ) &&
			$GLOBALS['wgWikiFarmConfigInternal']->get( 'shareUsers' ) &&
			$GLOBALS['wgWikiFarmConfigInternal']->get( 'useSharedResources' )
		) {
			return $this->createInstance(
				$GLOBALS['wgWikiFarmConfigInternal']->get( 'sharedResourcesWikiPath' ),
				Message::newFromKey( 'wikifarm-shared-instance-name' )->text(),
				[
					'config' => [
						'wgWikiFarmInitialAccessLevel' => 'protected'
					]
				]
			);
		}

		return true;
	}

	/**
	 * @param string $path
	 * @param string $display
	 * @return bool
	 * @throws \Exception
	 */
	private function createInstance( string $path, string $display, array $options ): bool {
		if ( !str_starts_with( $path, '-' ) ) {
			$this->error( "Path $path is not a system instance path\n" );
			return false;
		}
		/** @var InstanceManager $manager */
		$manager = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
		if ( $manager->getStore()->getInstanceByIdOrPath( $path ) ) {
			$this->output( "Instance $path already exists, skipping\n" );
			return true;
		}
		$instance = $manager->createEmptyInstance( $path, $display, $options );
		try {
			$id = $instance->getId();
			$executor = new CodeExecutor( [
				'create-instance-vault' => [
					'class' => CreateInstanceVault::class,
					'args' => [ $id ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager' ]
				],
				'install-instance' => [
					'class' => InstallInstance::class,
					'args' => [ $id, '' ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig', 'LanguageFactory' ]
				],
				'run-update' => [
					'class' => RunUpdates::class,
					'args' => [ $id ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
				],
				'run-post-instance-creation-commands' => [
					'class' => RunPostInstanceCreationTasks::class,
					'args' => [ $id ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
				]
			] );
			$executor->executeSteps();
		} catch ( Throwable $ex ) {
			$this->error( "Error creating instance $path: " . $ex->getMessage() );
			$this->purgeInstance( $instance );
		}

		return false;
	}

	/**
	 * @param InstanceEntity $instance
	 * @return void
	 * @throws \Exception#
	 */
	private function purgeInstance( InstanceEntity $instance ) {
		$executor = new CodeExecutor( [
			'purge-instance' => [
				'class' => PurgeInstance::class,
				'args' => [ $instance->getId() ],
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
			],
		] );
		$executor->executeSteps();
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-create-system-instances';
	}
}

$maintClass = CreateSystemInstances::class;
require_once RUN_MAINTENANCE_IF_MAIN;
