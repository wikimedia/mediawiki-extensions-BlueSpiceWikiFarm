$( () => {
	const $container = $( '#bs-access-management' );
	if ( $container.length ) {
		$container.append( new ext.bluespiceWikiFarm.ui.AccessManagementPanel( {
			wikiFarmIsRoot: mw.config.get( 'wikiFarmIsRoot' ),
			accessLevel: mw.config.get( 'wikiFarmAccessLevel' ),
			alwaysVisible: mw.config.get( 'wikiFarmAccessAlwaysVisible' )
		} ).$element );
	}
} );
