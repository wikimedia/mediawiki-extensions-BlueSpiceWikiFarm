<?php

namespace BlueSpice\WikiFarm\Process\Step;

use BlueSpice\WikiFarm\InstanceManager;
use Exception;
use MediaWiki\Message\Message;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\ILoadBalancer;

class CopyUser extends InstanceAwareStep {

	/** @var string */
	protected $username;

	/** @var ILoadBalancer */
	protected $lb;

	/**
	 * @param InstanceManager $instanceManager
	 * @param ILoadBalancer $lb
	 * @param string $instanceId
	 * @param string $username
	 * @throws Exception
	 */
	public function __construct(
		InstanceManager $instanceManager, ILoadBalancer $lb, string $instanceId, string $username
	) {
		parent::__construct( $instanceManager, $instanceId );
		$this->username = $username;
		$this->lb = $lb;
	}

	/**
	 * @param array|null $data
	 * @return array
	 * @throws Exception
	 */
	public function execute( $data = [] ): array {
		sleep( 5 );
		$instanceDb = $this->getInstanceManager()->getDatabaseConnectionForInstance( $this->getInstance() );
		if ( !$instanceDb ) {
			$this->getInstanceManager()->getLogger()->error( 'Could not get database connection for instance {path}', [
				'path' => $this->getInstance()->getPath()
			] );
		}

		$rootDb = $this->lb->getConnection( DB_REPLICA );
		$userData = $this->readUser( $rootDb );
		$sourceUserId = $userData['user_id'];
		unset( $userData['user_id'] );
		$targetUserId = $this->insertUser( $instanceDb, $userData );

		$this->syncUserProperties( $rootDb, $instanceDb, $sourceUserId, $targetUserId );
		$this->syncUserGroups( $rootDb, $instanceDb, $sourceUserId, $targetUserId );

		return $data;
	}

	/**
	 * @param IDatabase $db
	 * @return array
	 * @throws Exception
	 */
	protected function readUser( IDatabase $db ): array {
		$userRow = $db->selectRow( 'user', [ '*' ], [ 'user_name' => $this->username ], __METHOD__ );
		if ( !$userRow ) {
			$this->getInstanceManager()->getLogger()->error( "User {$this->username} not found" );
			throw new Exception( Message::newFromKey( 'wikifarm-error-unknown' )->text() );
		}
		return (array)$userRow;
	}

	/**
	 * @param IDatabase $db
	 * @param array $userData
	 * @return int
	 */
	protected function insertUser( IDatabase $db, array $userData ): int {
		$exist = $db->selectField(
			'user',
			'user_id',
			[ 'user_name' => $userData['user_name'] ],
			__METHOD__
		);

		if ( $exist ) {
			$this->getInstanceManager()->getLogger()->debug( "User {$userData['user_name']} already exists, updating" );
			$db->update( 'user', $userData, [ 'user_id' => $exist ], __METHOD__ );
			return (int)$exist;
		}
		$this->getInstanceManager()->getLogger()->debug( "User {$userData['user_name']} does not exist, inserting" );
		$db->startAtomic( __METHOD__ );
		$db->insert( 'user', $userData, __METHOD__ );
		$userId = $db->insertId();
		$db->insert( 'actor', [ 'actor_user' => $userId, 'actor_name' => $userData['user_name'] ], __METHOD__ );
		$db->endAtomic( __METHOD__ );
		return $userId;
	}

	/**
	 * @param IDatabase $rootDb
	 * @param IDatabase $instanceDb
	 * @param int $sourceUser
	 * @param int $targetUser
	 */
	protected function syncUserProperties(
		IDatabase $rootDb, IDatabase $instanceDb, int $sourceUser, int $targetUser
	): void {
		$this->truncate( $instanceDb, 'user_properties', [ 'up_user' => $targetUser ] );
		$userProperties = $rootDb->select(
			'user_properties',
			[ '*' ],
			[ 'up_user' => $sourceUser ],
			__METHOD__
		);
		$data = [];
		foreach ( $userProperties as $userProperty ) {
			$data[] = [
				'up_user' => $targetUser,
				'up_property' => $userProperty->up_property,
				'up_value' => $userProperty->up_value
			];
		}
		if ( $data ) {
			$instanceDb->insert( 'user_properties', $data, __METHOD__ );
		}
	}

	/**
	 * @param IDatabase $rootDb
	 * @param IDatabase $instanceDb
	 * @param int $sourceUser
	 * @param int $targetUser
	 */
	protected function syncUserGroups(
		IDatabase $rootDb, IDatabase $instanceDb, int $sourceUser, int $targetUser
	): void {
		$this->truncate( $instanceDb, 'user_groups', [ 'ug_user' => $targetUser ] );
		$userGroups = $rootDb->select(
			'user_groups',
			[ '*' ],
			[ 'ug_user' => $sourceUser ],
			__METHOD__
		);
		$data = [];
		$hasSysop = false;
		foreach ( $userGroups as $userGroup ) {
			$data[] = [
				'ug_user' => $targetUser,
				'ug_group' => $userGroup->ug_group,
				'ug_expiry' => $userGroup->ug_expiry
			];
			if ( $userGroup->ug_group === 'sysop' ) {
				$hasSysop = true;
			}
		}
		if ( !$hasSysop ) {
			$data[] = [
				'ug_user' => $targetUser,
				'ug_group' => 'sysop',
				'ug_expiry' => null
			];
		}
		if ( $data ) {
			$instanceDb->insert( 'user_groups', $data, __METHOD__ );
		}
	}

	/**
	 * @param IDatabase $db
	 * @param string $table
	 * @param array $conditions
	 * @return void
	 */
	private function truncate( IDatabase $db, string $table, array $conditions ): void {
		$db->delete( $table, $conditions, __METHOD__ );
	}
}
