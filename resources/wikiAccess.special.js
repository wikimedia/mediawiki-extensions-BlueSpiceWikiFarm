$( () => {
	const $gridCnt = $( '#bs-wiki-access' );
	if ( $gridCnt.length ) {
		$gridCnt.append( new ext.bluespiceWikiFarm.ui.WikiAccessPanel( {
			wikiFarmIsRoot: mw.config.get( 'wikiFarmIsRoot' ),
			accessLevel: mw.config.get( 'wikiFarmAccessLevel' ),
			alwaysVisible: mw.config.get( 'wikiFarmAccessAlwaysVisible' )
		} ).$element );
	}
} );
