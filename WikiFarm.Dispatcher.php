<?php

use BlueSpice\WikiFarm\DirectInstanceStore;
use BlueSpice\WikiFarm\ProcessQueue\FarmProcessQueue;
use MediaWiki\Config\GlobalVarConfig;
use MediaWiki\Config\MultiConfig;

define( 'WIKI_FARMING', true );

$GLOBALS['wgWikiFarmConfigInternal'] = new MultiConfig( [
	new GlobalVarConfig( 'wgWikiFarmConfig_' ),
	new BlueSpice\WikiFarm\DefaultConfig( $GLOBALS )
] );

$managementDBFactory = new BlueSpice\WikiFarm\ManagementDatabaseFactory( $GLOBALS['wgWikiFarmConfigInternal'] );
$GLOBALS['wgWikiFarmGlobalStore'] = new DirectInstanceStore( $managementDBFactory );

$GLOBALS['wgWikiFarmDispatcher'] = new BlueSpice\WikiFarm\Dispatcher(
	$_REQUEST, $GLOBALS['wgWikiFarmGlobalStore'], $GLOBALS['wgWikiFarmConfigInternal']
);
foreach ( $GLOBALS['wgWikiFarmDispatcher']->getFilesToRequire() as $pathname ) {
	require $pathname;
}

mwsInitComponents();
$GLOBALS['mwsgProcessManagerQueue'] = [
	'class' => FarmProcessQueue::class,
	'args' => [ FARMER_CALLED_INSTANCE, FARMER_IS_ROOT_WIKI_CALL ],
	'services' => [ 'DBLoadBalancer', 'BlueSpiceWikiFarm._Config' ]
];
