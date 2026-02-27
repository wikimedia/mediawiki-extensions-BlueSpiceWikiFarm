$( () => {
	let $cnt = $( '#farm-management' );
	if ( $cnt.length ) {
		$cnt.html(
			new ext.bluespiceWikiFarm.ui.ManagementPanel( {} ).$element
		);
		return;
	}
	$cnt = $( '#farm-create-instance' );
	if ( $cnt.length > 0 ) {
		mw.loader.using( 'ext.bluespice.wikiFarm.create' ).done( () => {
			$cnt.html(
				new ext.bluespiceWikiFarm.ui.CreatePanel( $cnt.data( 'params' ) ).$element
			);
		} );
		return;
	}

	$cnt = $( '#farm-edit-instance' );
	if ( $cnt.length > 0 ) {
		mw.loader.using( 'ext.bluespice.wikiFarm.edit' ).done( () => {
			$cnt.html(
				new ext.bluespiceWikiFarm.ui.EditPanel( {
					instanceData: $cnt.data( 'instance' )
				} ).$element
			);
		} );
		return;
	}

	// Selection
	$cnt = $( '#farm-create-instance-selection' );
	if ( $cnt.length > 0 ) {
		mw.loader.using( 'ext.bluespice.wikiFarm.selection' ).done( () => {
			$cnt.html(
				new ext.bluespiceWikiFarm.ui.InstanceSourcePanel( $cnt.data( 'params' ) ).$element
			);
		} );
		return;
	}

} );
