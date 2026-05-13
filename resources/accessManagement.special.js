$( () => {
	const $container = $( '#bs-access-management' );
	if ( $container.length ) {
		$container.append( new ext.bluespiceWikiFarm.ui.AccessManagementPanel( {
			wikiFarmIsRoot: mw.config.get( 'wikiFarmIsRoot' ),
			accessLevel: mw.config.get( 'wikiFarmAccessLevel' ),
			alwaysVisible: mw.config.get( 'wikiFarmAccessAlwaysVisible' )
		} ).$element );

		if ( $( document ).find( '#bs-accessManagement-skeleton-cnt' ) ) {
			$( '#bs-accessManagement-skeleton-cnt' ).empty();
		}
	}
} );
