<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\Process\Step\CopyUser;
use BlueSpice\WikiFarm\Process\Step\CreateInstanceVault;
use BlueSpice\WikiFarm\Process\Step\InstallInstance;
use BlueSpice\WikiFarm\Process\Step\PurgeInstance;
use BlueSpice\WikiFarm\Process\Step\RunPostInstanceCreationTasks;
use BlueSpice\WikiFarm\Process\Step\RunUpdates;
use BlueSpice\WikiFarm\Util\SystemInstancesHelper;
use MediaWiki\Message\Message;
use MWStake\MediaWiki\Component\ProcessManager\CodeExecutor;
use Throwable;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class CreateSystemInstances extends \MediaWiki\Maintenance\LoggedUpdateMaintenance {

	private const ROOT_USER_ID = 1;

	/**
	 * @return bool
	 * @throws \Exception
	 */
	protected function doDBUpdates() {
		/** @var SystemInstancesHelper $systemInstancesHelper */
		$systemInstancesHelper = $this->getServiceContainer()
			->getService( 'BlueSpiceWikiFarm._SystemInstancesHelper' );
		if ( $systemInstancesHelper->usesSharedResourcesInstance() ) {
			return $this->createInstance(
				$GLOBALS['wgWikiFarmConfigInternal']->get( 'sharedResourcesWikiPath' ),
				Message::newFromKey( 'wikifarm-shared-instance-name' )->text(),
				[
					'config' => [
						'wgWikiFarmInitialAccessLevel' => 'protected'
					],
					'metadata' => [
						'instanceColor' => [ 'background' => '#747474', 'lightText' => true ]
					],
					'isSystemInstance' => true
				]
			);
		}

		return true;
	}

	/**
	 * @param string $path
	 * @param string $display
	 * @param array $options
	 * @return bool
	 * @throws \Exception
	 */
	private function createInstance( string $path, string $display, array $options ): bool {
		$this->output( "Starting creation of instance $path\n" );
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
			$user = $this->getServiceContainer()->getUserFactory()->newFromId( self::ROOT_USER_ID );
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
				'copy-user' => [
					'class' => CopyUser::class,
					'args' => [ $id, $user->getName() ],
					'services' => [
						'BlueSpiceWikiFarm.InstanceManager', 'DBLoadBalancer', 'BlueSpiceWikiFarm.AccessControlStore',
						'UserFactory', 'BlueSpiceWikiFarm.InstanceGroupCreator', 'UserGroupManager'
					]
				],
				'run-update' => [
					'class' => RunUpdates::class,
					'args' => [ $id . '123' ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
				],
				'run-post-instance-creation-commands' => [
					'class' => RunPostInstanceCreationTasks::class,
					'args' => [ $id ],
					'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig' ]
				]
			] );
			$executor->executeSteps();

			$this->output( "Instance $path created\n" );
		} catch ( Throwable $ex ) {
			$this->error( "Error creating instance $path, rolling back:" . $ex->getMessage() );
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
				'services' => [ 'BlueSpiceWikiFarm.InstanceManager', 'MainConfig', 'MWStake.StorageUtilities',
					'BlueSpiceWikiFarm.WikiAccessLookup', 'BlueSpiceWikiFarm.InstanceGroupCreator', 'DBLoadBalancer' ]
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
