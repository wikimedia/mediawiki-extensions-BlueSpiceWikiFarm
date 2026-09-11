$( () => {
	require( './ui/panel/WikisPanel.js' );
	const $instancesCnt = $( '#bs-wikifarm-wikis' );
	if ( $instancesCnt.length ) {
		mw.user.getRights().done( ( rights ) => {
			const permissions = [];
			if ( rights.indexOf( 'wikifarm-managewiki' ) !== -1 ) {
				permissions.push( 'managewiki' );
			}
			const creation = $instancesCnt.data( 'creation' ) === 1;
			const panel = new bs.bluespiceWikiFarm.ui.WikisPanel( {
				permissions: permissions,
				creationAllowed: creation
			} );
			$instancesCnt.append( panel.$element );
		} );
	}
} );
