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
	_config: () => require( './farmConfig.json' )
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
		btn.addEventListener( 'click', ( e ) => {
			e.stopPropagation();
			if ( mw.user.isAnon() ) {
				return true;
			}

			const options = mw.user.options.get( 'bs-farm-instances-favorite' );
			const val = $( e.target ).parent().data( 'path' );
			let newVal = '';
			if ( $( btn ).hasClass( 'bi-star' ) ) {
				newVal = options + val + ',';
			} else {
				newVal = options.replace( val + ',', '' );
			}
			mw.loader.using( [ 'mediawiki.api' ] ).done( () => {
				mw.user.options.set( 'bs-farm-instances-favorite', newVal );
				new mw.Api().saveOption( 'bs-farm-instances-favorite', newVal );
				btn.classList.toggle( 'bi-star' );
				btn.classList.toggle( 'bi-star-fill' );
			} );
		} );
	} );
} );
