ext.bluespiceWikiFarm.ui.widget.TeamEntity = function ( cfg ) {
	cfg = cfg || {};
	cfg.icon = 'userGroup';
	cfg.framed = false;
	this.key = cfg.entity_key;
	cfg.label = this.key + ' (' + mw.msg( 'wikifarm-ui-access-assignee-type-team' ) + ')';
	cfg.href = mw.Title.makeTitle( -1, 'WikiTeams/' + this.key ).getUrl( {
		backTo: mw.config.get( 'wgPageName' )
	} );

	ext.bluespiceWikiFarm.ui.widget.TeamEntity.parent.call( this, cfg );
	this.$element.addClass( 'bs-wiki-access-team-entity' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.TeamEntity, OO.ui.ButtonWidget );
