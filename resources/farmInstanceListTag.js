$( () => {
	const $cnt = $( '.farm-instances-list' );
	if ( !$cnt.length ) {
		return;
	}

	$cnt.each( function () {
		$( this ).html(
			new ext.bluespiceWikiFarm.ui.InstancesGrid( {} ).$element
		);
	} );
} );
