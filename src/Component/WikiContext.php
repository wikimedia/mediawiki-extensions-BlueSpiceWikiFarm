<?php

namespace BlueSpice\WikiFarm\Component;

use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Context\IContextSource;
use MediaWiki\Html\Html;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;

class WikiContext extends Literal {

	/**
	 * @param InstanceStore $instanceStore
	 */
	public function __construct( private readonly InstanceStore $instanceStore ) {
		parent::__construct( 'wikifarm-wiki-context', '' );
	}

	/**
	 * @inheritDoc
	 */
	public function shouldRender( IContextSource $context ): bool {
		return $this->instanceStore->getCurrentInstance() !== null;
	}

	/**
	 * @inheritDoc
	 */
	public function getHtml(): string {
		$instance = $this->instanceStore->getCurrentInstance();
		if ( !$instance ) {
			return '';
		}

		$color = $instance->getMetadata()['instanceColor']['background'] ?? '#3e5389';

		return Html::element( 'div', [
			'id' => $this->getId(),
			'class' => 'wikifarm-wiki-context',
			'style' => "color: $color;"
		], $instance->getDisplayName() );
	}

	/**
	 * @inheritDoc
	 */
	public function getRequiredRLStyles(): array {
		return [ 'ext.bluespice.wikiFarm.wikiContext' ];
	}
}
