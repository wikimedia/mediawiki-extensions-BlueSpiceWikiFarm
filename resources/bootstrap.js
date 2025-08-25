window.ext = window.ext || {};
window.ext.bluespiceWikiFarm = {
	ui: {
		dialog: {},
		widget: {}
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
} );
