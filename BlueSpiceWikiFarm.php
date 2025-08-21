<?php

if ( file_exists( __DIR__ . '/BlueSpiceWikiFarm.local.php' ) ) {
	require_once __DIR__ . '/BlueSpiceWikiFarm.local.php';
} else {
	require_once __DIR__ . '/BlueSpiceWikiFarm.default.php';
}
