ext.bluespiceWikiFarm.ui.widget.AccessLevelInput = function ( cfg ) {
	cfg = cfg || {};
	cfg.menu = cfg.menu || {};

	cfg.menu.items = [
		new OOJSPlus.ui.widget.MenuOptionWithDescription( {
			data: 'public',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-public' ),
			description: mw.msg( 'wikifarm-ui-access-wiki-type-public-help' ),
			disabled: cfg.enabledItems ? cfg.enabledItems.indexOf( 'public' ) === -1 : false
		} ),
		new OOJSPlus.ui.widget.MenuOptionWithDescription( {
			data: 'protected',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-protected' ),
			description: mw.msg( 'wikifarm-ui-access-wiki-type-protected-help' ),
			disabled: cfg.enabledItems ? cfg.enabledItems.indexOf( 'protected' ) === -1 : false
		} ),
		new OOJSPlus.ui.widget.MenuOptionWithDescription( {
			data: 'private',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-private' ),
			description: mw.msg( 'wikifarm-ui-access-wiki-type-private-help' ),
			disabled: cfg.enabledItems ? cfg.enabledItems.indexOf( 'private' ) === -1 : false
		} )
	];

	cfg.classes = [ 'bs-wiki-access-type' ];

	ext.bluespiceWikiFarm.ui.widget.AccessLevelInput.parent.call( this, cfg );

	this.menu.selectItemByData( 'private' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.AccessLevelInput, OO.ui.DropdownWidget );
