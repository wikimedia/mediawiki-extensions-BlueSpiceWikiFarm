window.ext = window.ext || {};
window.ext.bluespiceWikiFarm = {
	ui: {
		dialog: {},
		widget: {}
	},
	ve: {
		dialog: {},
		dm: {}
	},
	formElement: {},
	_config: () => require( './farmConfig.json' ),
	util: {
		toggleFavoriteInstance: async ( path, display ) => {
			try {
				const options = mw.user.options.get( 'bs-farm-instances-favorite' );
				let currentFavorites = options ? options.split( ',' ).map( ( item ) => item.trim() ) : [];
				// Only get unique
				currentFavorites = [ ...new Set( currentFavorites ) ];

				const action = currentFavorites.includes( path ) ? 'remove' : 'add';
				if ( action === 'add' ) {
					currentFavorites.push( path );
				} else {
					const index = currentFavorites.indexOf( path );
					if ( index > -1 ) {
						currentFavorites.splice( index, 1 );
					}
				}

				const newValue = currentFavorites.join( ',' );
				await mw.loader.using( [ 'mediawiki.api' ] );
				mw.user.options.set( 'bs-farm-instances-favorite', newValue );
				await new mw.Api().saveOption( 'bs-farm-instances-favorite', newValue );
				mw.notify(
					// The following messages are used here:
					// * wikifarm-instances-favourite-notification-add
					// * wikifarm-instances-favourite-notification-remove
					mw.message( 'wikifarm-instances-favourite-notification-' + action, display || path ).text(),
					{ type: 'success' }
				);
				return action;
			} catch {
				mw.notify( mw.message( 'wikifarm-instances-favourite-notification-error' ).text(), { type: 'error' } );
				return null;
			}
		},
		getWikiBadge: ( source, maxLength ) => {
			let text = source.display_text;
			if ( maxLength > 0 ) {
				text = text.length > maxLength ? text.slice( 0, maxLength - 1 ) + '…' : text;
			}
			const $badge = $( '<div>' ).addClass( 'wikifarm-wiki-badge' );
			const $icon = $( '<span>' ).addClass( 'wikifarm-wiki-badge__icon' );
			const $text = $( '<span>' ).addClass( 'wikifarm-wiki-badge__text' ).text( text );

			$badge.append( $icon, $text );

			const wikiColor = ext.bluespiceWikiFarm.util.getWikiColor( source );
			$badge.css( 'color', wikiColor );
			$icon.css( 'background-color', wikiColor );
			if ( ext.bluespiceWikiFarm.util.shouldUseLightText( source ) ) {
				$badge.addClass( 'wikifarm-wiki-badge--light-text' );
			}
			return $badge;
		},
		getWikiColor: ( source ) => {
			if ( !source.color ) {
				return '#3e5389';
			}
			return source.color.background;
		},
		shouldUseLightText: ( source ) => {
			if ( !source.color ) {
				return false;
			}
			return source.color.lightText || false;
		},
		getWikiInfoFromWikiID: async ( wikiId ) => {
			// Make an API request to get the wiki info from the wiki_id
			// eg. rest.php/bluespice/farm/v1/wiki_info/wiki_b8c31707
			try {
				const response = await fetch( mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/wiki_info/' + wikiId );
				if ( !response.ok ) {
					throw new Error( 'Failed to retrieve wiki information' );
				}
				return await response.json();
			} catch ( error ) {
				console.error( 'Failed to fetch wiki info:', error ); // eslint-disable-line no-console
				return null;
			}
		}
	}
};

$( () => {
	const $actionButton = $( '#ca-shared-promote' );
	if ( $actionButton.length > 0 ) {
		$actionButton.on( 'click', ( e ) => {
			e.preventDefault();
			mw.loader.using( [ 'ext.bluespice.wikiFarm.promotionToShared' ], () => {
				const dialog = new ext.bluespiceWikiFarm.ui.dialog.PromoteToSharedDialog( {
					page: mw.config.get( 'wgPageName' ),
					pageId: mw.config.get( 'wgArticleId' )
				} );
				const wm = OO.ui.getWindowManager();
				wm.addWindows( [ dialog ] );
				wm.openWindow( dialog ).closed.then( ( moved ) => {
					if ( moved ) {
						window.location.reload();
					}
				} );
			}, () => {
				console.error( 'Failed to load the promotion dialog module' ); // eslint-disable-line no-console
			} );
		} );
	}

	document.querySelectorAll( '.farm-wiki-card-favorite-btn' ).forEach( ( btn ) => { // eslint-disable-line mediawiki/no-nodelist-unsupported-methods
		btn.addEventListener( 'click', async ( e ) => {
			e.stopPropagation();
			if ( mw.user.isAnon() ) {
				return;
			}

			const instanceItem = e.target.closest( '.farm-wiki-card-item' );
			const path = instanceItem ? instanceItem.dataset.path : null;
			if ( !path ) {
				return;
			}
			const action = await ext.bluespiceWikiFarm.util.toggleFavoriteInstance( path, instanceItem.dataset.display );
			if ( action === 'add' ) {
				btn.classList.remove( 'bi-bs-unfavored' );
				btn.classList.add( 'bi-bs-favored' );
			} else if ( action === 'remove' ) {
				btn.classList.remove( 'bi-bs-favored' );
				btn.classList.add( 'bi-bs-unfavored' );
			}
		} );
	} );

} );

mw.hook( 'oojsplus.ui.widget.batchoptionwidget.preinit' ).add( ( item, $element ) => {
	if ( !item.data.includes( 'source' ) ) {
		return;
	}
	if ( item.attr.length === 0 ) {
		return;
	}
	if ( item.attr.is_root ) {
		$( item.$label ).addClass( 'bi-bs-home' );
		return;
	}
	if ( item.attr && item.attr.color ) {
		$( $element ).css( 'border-left', '4px solid ' + item.attr.color.background );
	}
} );

mw.hook( 'bs.extendedSearch.AC.result' ).add( ( result ) => {
	if ( !result.source ) {
		return;
	}
	let bgColor = '';
	let color = '#fff';
	if ( result.source.color.background ) {
		bgColor = result.source.color.background;
	}
	if ( !result.source.color.lightText ) {
		color = '#000';
	}

	const $source = $( '<span>' )
		.addClass( 'bs-extendedsearch-autocomplete-popup-item-header-path-source' )
		.css( 'background-color', bgColor )
		.css( 'color', color )
		.text( result.source.display_text );

	result.$header.find( '.bs-extendedsearch-autocomplete-popup-item-header-path' )
		.prepend( $source );
} );

mw.hook( 'bs.extendedSearch.result.init' ).add( ( $element, source ) => {
	if ( !source ) {
		return;
	}

	const defaultColor = '#747474';
	const wikiColor = source.color && source.color.background ?
		source.color.background : defaultColor;

	$element.css( '--wiki-color', wikiColor );

	const $badge = $( '<div>' ).addClass( 'wikifarm-wiki-badge' );
	const $icon = $( '<span>' )
		.addClass( 'wikifarm-wiki-badge__icon' )
		.css( 'background-color', wikiColor );
	const $text = $( '<span>' )
		.addClass( 'wikifarm-wiki-badge__text' )
		.text( source.display_text );
	$badge.append( $icon, $text ).css( 'color', wikiColor );
	if ( source.color && source.color.lightText ) {
		$badge.addClass( 'farm-wiki-badge--light-text' );
	}

	$element.find( '.bs-extendedsearch-result-wiki-label' ).append( $badge );
} );

mw.hook( 'notifyme.notification.item' ).add( ( notification, data ) => {
	if ( !data.sourceWiki ) {
		return;
	}

	const $wikiBadge = ext.bluespiceWikiFarm.util.getWikiBadge( data.sourceWiki );
	notification.$content.prepend( $wikiBadge );

	const wikiColor = ext.bluespiceWikiFarm.util.getWikiColor( data.sourceWiki );
	if ( wikiColor ) {
		notification.$element.css( 'border-left', `4px solid ${ wikiColor }` );
	}
} );

mw.hook( 'notifyme.notification.group.item' ).add( ( notification, data ) => {
	if ( !data._source_wiki ) { // eslint-disable-line no-underscore-dangle
		return;
	}
	const source = data._source_wiki; // eslint-disable-line no-underscore-dangle

	const $wikiBadge = ext.bluespiceWikiFarm.util.getWikiBadge( source );
	notification.$content.prepend( $wikiBadge );

	const wikiColor = ext.bluespiceWikiFarm.util.getWikiColor( source );
	if ( wikiColor ) {
		notification.$element.css( 'border-left', `4px solid ${ wikiColor }` );
	}
} );

mw.hook( 'notifyme.notification.preview.item' ).add( ( notification, data ) => {
	if ( !data._source_wiki ) { // eslint-disable-line no-underscore-dangle
		return;
	}
	const source = data._source_wiki; // eslint-disable-line no-underscore-dangle

	const $wikiBadge = ext.bluespiceWikiFarm.util.getWikiBadge( source, 40 );
	notification.$element.prepend( $wikiBadge );
	notification.$element.addClass( 'notification-preview-item-has-wiki-badge' );
	notification.$unreadCircle.css( 'background-color',
		ext.bluespiceWikiFarm.util.getWikiColor( source ) );

	const wikiColor = ext.bluespiceWikiFarm.util.getWikiColor( source );
	if ( wikiColor ) {
		notification.$element.css( 'border-left', `4px solid ${ wikiColor }` );
	}
} );

mw.hook( 'chatbot.source.foreignWikiTitle' ).add( async ( data ) => {
	const instanceInfo = await ext.bluespiceWikiFarm.util.getWikiInfoFromWikiID( data.wikiId );
	if ( !instanceInfo ) {
		return;
	}
	const foreignTitle = mw.Title.makeTitle( data.namespaceId, instanceInfo.interwiki + ':' + data.titleText );
	const $wikiBadge = ext.bluespiceWikiFarm.util.getWikiBadge( instanceInfo, 16 );

	data.anchor.href = data.url;
	data.anchor.title = foreignTitle.getPrefixedText();
	if ( data.anchor.classList.contains( 'reference-link' ) &&
		$wikiBadge && $wikiBadge.length &&
		!data.anchor.querySelector( '.chatbot-source-wiki-badge' ) ) {
		$wikiBadge.addClass( 'chatbot-source-wiki-badge' );
		$( data.anchor ).prepend( $wikiBadge, ' ' );
	}
} );
