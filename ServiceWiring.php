<?php

use BlueSpice\WikiFarm\AccessControl\GroupAccessStore;
use BlueSpice\WikiFarm\AccessControl\InstanceGroupCreator;
use BlueSpice\WikiFarm\AccessControl\NullAccessStore;
use BlueSpice\WikiFarm\AccessControl\TeamManager;
use BlueSpice\WikiFarm\ForeignRequestExecution;
use BlueSpice\WikiFarm\GlobalDatabaseQueryExecution;
use BlueSpice\WikiFarm\InstanceCountLimiter;
use BlueSpice\WikiFarm\InstanceManager;
use BlueSpice\WikiFarm\InstancePathGenerator;
use BlueSpice\WikiFarm\InstanceStore;
use BlueSpice\WikiFarm\ManagementDatabaseFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

return [
	'BlueSpiceWikiFarm.InstanceStore' => static function ( MediaWikiServices $services ) {
		return new InstanceStore(
			$services->getService( 'BlueSpiceWikiFarm.ManagementDatabaseFactory' )
		);
	},
	'BlueSpiceWikiFarm.InstanceManager' => static function ( MediaWikiServices $services ) {
		return new InstanceManager(
			$services->getService( 'BlueSpiceWikiFarm.InstanceStore' ),
			$services->getService( 'ProcessManager' ),
			LoggerFactory::getInstance( 'BlueSpiceWikiFarm' ),
			$services->getService( 'BlueSpiceWikiFarm._Config' ),
			$services->getMainConfig(),
			$services->getDatabaseFactory(),
			$services->getService( 'BlueSpiceWikiFarm._InstanceCountLimiter' ),
			$services->getService( 'BlueSpiceWikiFarm._InstancePathGenerator' )
		);
	},
	'BlueSpiceWikiFarm._Config' => static function ( MediaWikiServices $services ) {
		return $services->getMainConfig()->get( 'WikiFarmConfigInternal' );
	},
	'BlueSpiceWikiFarm._InstanceCountLimiter' => static function ( MediaWikiServices $services ) {
		return new InstanceCountLimiter(
			$services->getService( 'BlueSpiceWikiFarm._Config' ),
			$services->getService( 'BlueSpiceWikiFarm.InstanceStore' )
		);
	},
	'BlueSpiceWikiFarm._InstancePathGenerator' => static function ( MediaWikiServices $services ) {
		return new InstancePathGenerator(
			$services->getService( 'BlueSpiceWikiFarm.InstanceStore' )
		);
	},
	'BlueSpiceWikiFarm.GlobalDatabaseQuery' => static function ( MediaWikiServices $services ) {
		$config = $services->getService( 'BlueSpiceWikiFarm._Config' );
		return new GlobalDatabaseQueryExecution(
			$services->getService( 'BlueSpiceWikiFarm.ManagementDatabaseFactory' ),
			$GLOBALS['wgWikiFarmGlobalStore'],
			$services->getDBLoadBalancer(),
			$services->getService( 'BlueSpiceWikiFarm._Config' ),
			LoggerFactory::getInstance( 'BlueSpiceWikiFarm' ),
			$services->getService( 'BlueSpiceWikiFarm.AccessControlStore' ),
			(bool)$config->get( 'shareUsers' )
		);
	},
	'BlueSpiceWikiFarm.AccessControlStore' => static function ( MediaWikiServices $services ) {
		$config = $services->getService( 'BlueSpiceWikiFarm._Config' );
		if ( !$config->get( 'shareUsers' ) ) {
			return new NullAccessStore();
		}
		return new GroupAccessStore(
			$services->getService( 'BlueSpiceWikiFarm.ManagementDatabaseFactory' ),
			$services->getService( 'BlueSpiceWikiFarm.InstanceGroupCreator' ),
			$services->getService( 'BlueSpiceWikiFarm.TeamManager' )
		);
	},
	'BlueSpiceWikiFarm.ManagementDatabaseFactory' => static function ( MediaWikiServices $services ) {
		return new ManagementDatabaseFactory( $services->getService( 'BlueSpiceWikiFarm._Config' ) );
	},
	'BlueSpiceWikiFarm.TeamManager' => static function ( MediaWikiServices $services ) {
		return new TeamManager(
			$services->getDBLoadBalancer()->getConnection( DB_PRIMARY ),
			$services->getUserGroupManager(),
			$services->getUserFactory(),
			LoggerFactory::getInstance( 'BlueSpiceWikiFarm.AccessControl' )
		);
	},
	'BlueSpiceWikiFarm.InstanceGroupCreator' => static function ( MediaWikiServices $services ) {
		return new InstanceGroupCreator( $GLOBALS['wgWikiFarmGlobalStore'] );
	},
	'BlueSpiceWikiFarm.ForeignRequestExecution' => static function ( MediaWikiServices $services ) {
		return new ForeignRequestExecution(
			$services->getHttpRequestFactory(),
			$services->getService( 'BlueSpiceWikiFarm._Config' )
		);
	},
];
