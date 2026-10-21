<?php

namespace BlueSpice\WikiFarm\Hook;

use BlueSpice\WikiFarm\Event\ExportPackageReadyEvent;

class RegisterNotifyMeEvents {

	/**
	 * @param array &$events
	 * @return void
	 */
	public function onNotifyMeRegisterEvents( array &$events ): void {
		$events['wikifarm-export-package-ready'] = [
			'spec' => [
				'class' => ExportPackageReadyEvent::class
			],
			'buckets' => [ 'personal' ],
			'description' => 'wikifarm-notifyme-event-export-package-ready-short-desc'
		];
	}
}
