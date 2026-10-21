<?php

namespace BlueSpice\WikiFarm\Event;

use MediaWiki\Message\Message;
use MediaWiki\User\UserIdentity;
use MWStake\MediaWiki\Component\Events\BotAgent;
use MWStake\MediaWiki\Component\Events\Delivery\IChannel;
use MWStake\MediaWiki\Component\Events\NotificationEvent;

class ExportPackageReadyEvent extends NotificationEvent {

	public function __construct(
		private readonly UserIdentity $targetUser,
		private readonly string $instanceName,
		private readonly string $packageName,
		private readonly string $packageUrl,
		private readonly string $checksum
	) {
		parent::__construct( new BotAgent() );
	}

	public function getKey(): string {
		return 'wikifarm-export-package-ready';
	}

	public function getKeyMessage(): Message {
		return Message::newFromKey( 'wikifarm-notifyme-event-export-package-ready-key-desc' );
	}

	public function getMessage( IChannel $forChannel ): Message {
		return Message::newFromKey( 'wikifarm-notifyme-event-export-package-ready' )->params(
			$this->packageName,
			$this->packageUrl,
			$this->checksum,
			$this->instanceName
		);
	}

	public function getPresetSubscribers(): ?array {
		return [ $this->targetUser ];
	}

	public function getLinks( IChannel $forChannel ): array {
		return [];
	}
}
