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

	if ( mw.config.get( 'wgNamespaceNumber' ) === -1 && mw.config.get( 'wgTitle' ) === 'Instances' ) {
		require( './ui/panel/UserInstancePanel.js' );
		const $instancesCnt = $( '#bs-wikifarm-user-instances' );
		if ( $instancesCnt.length ) {
			const panel = new bs.bluespiceWikiFarm.ui.UserInstancePanel();
			$instancesCnt.append( panel.$element );
		}
	}
} );
