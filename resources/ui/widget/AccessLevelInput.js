ext.bluespiceWikiFarm.ui.widget.AccessLevelInput = function ( cfg ) {
	cfg = cfg || {};

	cfg.items = [
		new typeOptionWidget( { // eslint-disable-line new-cap, no-use-before-define
			data: 'public',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-public' ),
			help: mw.msg( 'wikifarm-ui-access-wiki-type-public-help' ),
			disabled: cfg.enabledItems ? cfg.enabledItems.indexOf( 'public' ) === -1 : false
		} ),
		new typeOptionWidget( { // eslint-disable-line new-cap, no-use-before-define
			data: 'protected',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-protected' ),
			help: mw.msg( 'wikifarm-ui-access-wiki-type-protected-help' ),
			disabled: cfg.enabledItems ? cfg.enabledItems.indexOf( 'protected' ) === -1 : false
		} ),
		new typeOptionWidget( { // eslint-disable-line new-cap, no-use-before-define
			data: 'private',
			label: mw.msg( 'wikifarm-ui-access-wiki-type-private' ),
			help: mw.msg( 'wikifarm-ui-access-wiki-type-private-help' ),
			disabled: cfg.enabledItems ? cfg.enabledItems.indexOf( 'private' ) === -1 : false
		} )
	];

	cfg.classes = [ 'bs-wiki-access-type' ];

	ext.bluespiceWikiFarm.ui.widget.AccessLevelInput.parent.call( this, cfg );

	this.selectItemByData( 'private' );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.AccessLevelInput, OO.ui.RadioSelectWidget );

const typeOptionWidget = function ( cfg ) {
	typeOptionWidget.parent.call( this, cfg );
	this.$element.append( $( '<span>' ).addClass( 'bs-wiki-access-type-label' ).append( cfg.help ) );
};
OO.inheritClass( typeOptionWidget, OO.ui.RadioOptionWidget );
