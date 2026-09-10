<?php

namespace BlueSpice\WikiFarm\BackLinkProvider;

use BlueSpice\Discovery\IBackLinkProvider;
use BlueSpice\WikiFarm\InstanceEntity;
use BlueSpice\WikiFarm\InstanceStore;
use MediaWiki\Html\Html;
use MediaWiki\Language\RawMessage;
use MediaWiki\Message\Message;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MWStake\MediaWiki\Component\CommonUserInterface\Component\Literal;

class WikiBackLinkProvider implements IBackLinkProvider {

	/** @var Title|null */
	private $backToTitle = null;

	/** @var Title|null */
	private $displayTitle = null;

	/** @var InstanceEntity */
	private $wiki = null;

	/**
	 * @param TitleFactory $titleFactory
	 * @param InstanceStore $instanceStore
	 */
	public function __construct(
		private readonly TitleFactory $titleFactory,
		private readonly InstanceStore $instanceStore
	) {
	}

	/**
	 * @inheritDoc
	 */
	public function applies( $context ): bool {
		$backToValue = $context->getRequest()->getVal( 'backTo' );
		if ( !$backToValue ) {
			return false;
		}

		if ( !str_starts_with( $backToValue, 'w' ) &&
			!str_starts_with( strtolower( $backToValue ), 'wiki-' ) ) {
			return false;
		}
		$valueParts = explode( ':', $backToValue );
		$prefix = $valueParts[0];

		if ( str_starts_with( strtolower( $prefix ), 'wiki-' ) ) {
			$prefix = str_replace( 'wiki-', '', $prefix );
		}
		$wiki = $this->instanceStore->getInstanceByPath( $prefix );
		if ( !$wiki ) {
			return false;
		}
		$titleText = str_replace( 'wiki-' . $prefix, '', $backToValue );
		$this->displayTitle = $this->titleFactory->newFromText( urldecode( $titleText ) );
		if ( !( $this->displayTitle instanceof Title ) ) {
			return false;
		}
		$interwikiTitle = Title::makeTitle( $this->displayTitle->getNamespace(), $this->displayTitle->getText(),
			'', $wiki->getPath() );
		$this->backToTitle = $interwikiTitle;
		$this->wiki = $wiki;
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function getHref(): string {
		return $this->backToTitle->getFullURL();
	}

	/**
	 * @inheritDoc
	 */
	public function getLabel(): Message {
		// use displayTitle since we want to show full title text but without interwiki prefix
		return new RawMessage( $this->displayTitle->getFullText() );
	}

	/**
	 * @inheritDoc
	 */
	public function getTitle(): Message {
		return Message::newFromKey( 'wikifarm-wiki-back-link-title', $this->backToTitle->getText() );
	}

	/**
	 * @inheritDoc
	 */
	public function getAriaLabel(): Message {
		return Message::newFromKey( 'wikifarm-wiki-back-link-aria-label', $this->backToTitle->getText() );
	}

	/**
	 * @inheritDoc
	 */
	public function getPreComponents(): array {
		$color = $this->wiki->getMetadata()['instanceColor'];
		$bg = $color['background'] ?? '#3e5389';
		$fg = $color['lightText'] ? '#fff' : '#000';

		$html = Html::element( 'span', [
			'class' => 'badge wikifarm-back-link-badge',
			'style' => "background-color:$bg;color:$fg;"
		], $this->wiki->getDisplayName() );

		return [ new Literal( 'wiki-badge', $html ) ];
	}
}
