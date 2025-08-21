$( () => {
	const $gridCnt = $( '#bs-wiki-teams-grid' );
	if ( $gridCnt.length ) {
		$gridCnt.append( new ext.bluespiceWikiFarm.ui.TeamsPanel( {} ).$element );
	}
	const $detailsCnt = $( '#bs-wiki-team-details' );
	if ( $detailsCnt.length ) {
		$detailsCnt.append( new ext.bluespiceWikiFarm.ui.TeamDetails( {
			teamData: $detailsCnt.data( 'team' )
		} ).$element );
	}
} );
