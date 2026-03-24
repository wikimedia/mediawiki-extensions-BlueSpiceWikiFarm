$( () => {
	require( './ui/panel/UserInstancePanel.js' );
	const $instancesCnt = $( '#bs-wikifarm-user-instances' );
	if ( $instancesCnt.length ) {
		const panel = new bs.bluespiceWikiFarm.ui.UserInstancePanel();
		$instancesCnt.append( panel.$element );
	}
} );
