<?php

namespace BlueSpice\WikiFarm\Hook;

use InvalidArgumentException;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Html\Html;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\PPFrame;

class HandleTag implements ParserFirstCallInitHook {

	/**
	 * @param Parser $parser
	 * @throws InvalidArgumentException
	 */
	public function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'bs-wikifarmer', [ $this, 'handleTag' ] );
		$parser->setHook( 'bs:wikifarmlist', [ $this, 'handleTag' ] );
		$parser->setHook( 'wikifarmlist', [ $this, 'handleTag' ] );
	}

	/**
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 *
	 * @return string
	 */
	public function handleTag( $input, array $args, Parser $parser, PPFrame $frame ) { // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
		$parser->getOutput()->addModules( [
			'ext.bluespice.wikiFarm.tag.wikifarmlist'
		] );

		return Html::element(
			'div', [
				'class' => 'farm-instances-list'
			]
		);
	}
}
