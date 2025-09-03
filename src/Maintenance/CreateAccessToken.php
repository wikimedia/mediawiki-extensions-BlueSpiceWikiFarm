<?php

namespace BlueSpice\WikiFarm\Maintenance;

use BlueSpice\WikiFarm\InstanceStore;
use Exception;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\OAuth\Backend\Consumer;
use MediaWiki\Extension\OAuth\Control\ConsumerSubmitControl;
use MediaWiki\Extension\OAuth\Entity\AccessTokenEntity;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\MaintenanceFatalError;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\User;
use MWRestrictions;
use Psr\Log\LoggerInterface;

require_once dirname( __FILE__, 5 ) . '/maintenance/Maintenance.php';

class CreateAccessToken extends \MediaWiki\Maintenance\LoggedUpdateMaintenance {

	/**
	 * @var LoggerInterface
	 */
	private LoggerInterface $logger;

	/**
	 * @return string|null
	 * @throws MaintenanceFatalError|Exception
	 */
	private function generateAccessToken(): ?string {
		$user = User::newSystemUser( 'ContentTransferBot', [ 'steal' => true ] );
		if ( !$user ) {
			throw new Exception( 'Failed to create user' );
		}
		$this->getServiceContainer()->getUserGroupManager()->addUserToMultipleGroups( $user, [ 'bot', 'sysop' ] );
		$user->setEmail( 'contenttransfer@default.com' );
		$user->confirmEmail();

		$data = [
			'action' => 'propose',
			'name'         => 'ContentTransfer',
			'version'      => '1.0',
			'description'  => 'ContentTransfer client',
			'oauthVersion' => 2,
			'callbackUrl' => 'https://dummy.com',
			'grants' => json_encode( [
				'editpage', 'createeditmovepage', 'uploadfile', 'uploadeditmovefile', 'highvolume'
			] ),
			'granttype' => 'normal',
			'ownerOnly' => true,
			'oauth2IsConfidential' => true,
			'oauth2GrantTypes' => [ 'client_credentials' ],
			'email' => $user->getEmail(),
			// All wikis
			'wiki' => '*',
			// Generate a key
			'rsaKey' => '',
			'agreement' => true,
			'restrictions' => MWRestrictions::newDefault(),
		];

		$context = RequestContext::getMain();
		$context->setUser( $user );

		$dbw = $this->getServiceContainer()->getDBLoadBalancer()->getConnectionRef( DB_PRIMARY );
		$control = new ConsumerSubmitControl( $context, $data, $dbw );
		$status = $control->submit();

		if ( !$status->isGood() ) {
			throw new Exception( $status->getMessage()->text() );
		}

		/** @var Consumer $cmr */
		// @phan-suppress-next-line PhanTypeArraySuspiciousNullable
		$cmr = $status->value['result']['consumer'];

		$data = [
			'action' => 'approve',
			'consumerKey'  => $cmr->getConsumerKey(),
			'reason'       => 'Approved by maintenance script',
			'changeToken'  => $cmr->getChangeToken( $context ),
		];
		$control = new ConsumerSubmitControl( $context, $data, $dbw );
		$approveStatus = $control->submit();

		if ( isset( $approveStatus ) ) {
			/** @var AccessTokenEntity $at */
			$at = $status->value['result']['accessToken'];
			return (string)$at;
		}

		return null;
	}

	/**
	 * @return bool
	 * @throws Exception
	 * @throws MaintenanceFatalError
	 */
	protected function doDBUpdates() {
		$this->logger = LoggerFactory::getInstance( 'BlueSpiceWikiFarm' );
		/** @var InstanceStore $store */
		$store = $this->getServiceContainer()->getService( 'BlueSpiceWikiFarm.InstanceStore' );
		$this->logger->debug( 'CreateAccessToken: Starting' );
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'OAuth' ) ) {
			$this->logger->info( 'CreateAccessToken: OAuth extension is not enabled' );
			$this->output( 'OAuth extension is not enabled' );
			return false;
		}
		if ( !defined( 'WIKI_FARMING' ) ) {
			$this->logger->info( 'CreateAccessToken: Wiki farming is not enabled' );
			$this->output( 'Wiki farming is not enabled' );
			return false;
		}
		if ( FARMER_IS_ROOT_WIKI_CALL ) {
			$this->output( 'This script must be run on a farm instance' );
			return false;
		}
		$instance = $store->getInstanceByPath( FARMER_CALLED_INSTANCE );
		if ( !$instance ) {
			$this->logger->info( 'CreateAccessToken: Instance not found' );
			$this->output( 'Instance not found' );
			return false;
		}

		$instanceConfig = $instance->getConfig();
		$accessToken = $instanceConfig['access_token'] ?? null;
		if ( $accessToken ) {
			$this->logger->info( 'CreateAccessToken: Access token already exists' );
			$this->output( "Access token already exists\n" );
			return false;
		}
		try {
			$accessToken = $this->generateAccessToken();
			if ( !$accessToken ) {
				$this->logger->error( 'CreateAccessToken: Failed to generate access token' );
				$this->output( 'Failed to generate access token' );
				return false;
			}
			$instance->setConfigItem( 'access_token', $accessToken );
			$store->store( $instance );
			$this->logger->info( 'CreateAccessToken: Access token generated' );
			$this->output( "Access token generated\n" );
			return true;
		} catch ( \Throwable $ex ) {
			$this->logger->error( 'CreateAccessToken: Failed to generate or store access token: ' . $ex->getMessage() );
			$this->error( 'Failed to generate or store access token: ' . $ex->getMessage() );
			return false;
		}
	}

	/**
	 * @return string
	 */
	protected function getUpdateKey() {
		return 'wikifarm-create-access-token';
	}
}

$maintClass = CreateAccessToken::class;
require_once RUN_MAINTENANCE_IF_MAIN;
