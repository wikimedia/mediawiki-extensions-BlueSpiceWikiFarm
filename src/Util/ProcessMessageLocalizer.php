<?php

namespace BlueSpice\WikiFarm\Util;

use MediaWiki\Language\Language;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Message\Message;

class ProcessMessageLocalizer implements MessageLocalizer {

	/**
	 * @param Language $language
	 */
	public function __construct(
		private readonly Language $language
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function msg( $key, ...$params ) {
		return Message::newFromKey( $key )->params( $params )->inLanguage( $this->language );
	}
}
