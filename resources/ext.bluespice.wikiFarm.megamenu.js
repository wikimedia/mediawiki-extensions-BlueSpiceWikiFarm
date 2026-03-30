$( () => {
	const $megaMenuButton = $( '#farm-wikis-btn' );
	const $megamenu = $( '#farm-wikis-mm' );

	require( './ui/panel/InstancesMenuPanel.js' );

	$megaMenuButton.one( 'click', () => {
		const instances = new ext.bluespiceWikiFarm.ui.InstancesMenuPanel();
		$megamenu.append( instances.$element );
	} );
} );
