<?php

use BlueSpice\WikiFarm\DirectInstanceStore;
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
$GLOBALS['wgWikiFarmDispatcher']->afterConfiguration();

mwsInitComponents();

$GLOBALS['mwsgProcessManagerQueueConfig']['farm-shared-database'] = [
	'class' => \BlueSpice\WikiFarm\ProcessQueue\FarmProcessQueue::class,
	'args' => [ FARMER_CALLED_INSTANCE, FARMER_IS_ROOT_WIKI_CALL ],
	'services' => [ 'DBLoadBalancer', 'BlueSpiceWikiFarm._Config' ]
];

if ( !$GLOBALS['mwsgProcessManagerQueue'] || $GLOBALS['mwsgProcessManagerQueue'] === 'local' ) {
	$GLOBALS['mwsgProcessManagerQueue'] = 'farm-shared-database';
}

$GLOBALS['mwsgWikiCronStore'] = [
	'class' => \BlueSpice\WikiFarm\ProcessQueue\WikiCronDatabaseStore::class,
	'args' => [ FARMER_CALLED_INSTANCE, FARMER_IS_ROOT_WIKI_CALL ],
	'services' => [ 'DBLoadBalancer', 'BlueSpiceWikiFarm._Config' ]
];

$GLOBALS['wgFileBackends']['_instances'] = [
	'name' => '_instances',
	'class' => FSFileBackend::class,
	'lockManager' => 'fsLockManager',
	'containerPaths' => [
		'instances-public' => $GLOBALS['wgWikiFarmConfigInternal' ]->get( 'instanceDirectory' ),
		'archive-public' => $GLOBALS['wgWikiFarmConfigInternal' ]->get( 'archiveDirectory' )
	],
	'fileMode' => $info['fileMode'] ?? 0644,
	'directoryMode' => $GLOBALS['wgDirectoryMode'],
];
$GLOBALS['wgWikiFarmConfig_instanceStorageBackend'] = '_instances';
