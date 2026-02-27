ext.bluespiceWikiFarm.ui.createEditorToolbar = function ( submitLabel, additionalItems ) {
	const toolFactory = new OO.ui.ToolFactory();
	const toolGroupFactory = new OO.ui.ToolGroupFactory();
	const toolbar = new OO.ui.Toolbar( toolFactory, toolGroupFactory );
	toolbar.$element.addClass( 'bluespiceWikiFarm-editor-toolbar' );

	const additionalItemKeys = [];

	for ( const item of ( additionalItems || [] ) ) {
		const visible = item.hasOwnProperty( 'visible' ) ? item.visible : true;
		if ( !visible ) {
			continue;
		}
		const CustomTool = function () {
			CustomTool.super.apply( this, arguments );
		};
		OO.inheritClass( CustomTool, OO.ui.Tool );
		CustomTool.static.name = item.data;
		CustomTool.static.title = item.label;
		CustomTool.static.displayBothIconAndLabel = true;
		CustomTool.static.label = item.label;
		if ( item.icon ) {
			CustomTool.static.icon = item.icon;
		}
		if ( item.flags ) {
			CustomTool.static.flags = item.flags;
		}
		CustomTool.prototype.onSelect = function () {
			toolbar.emit( item.data );
			return true;
		};
		CustomTool.prototype.onUpdateState = function () {};
		toolFactory.register( CustomTool );
		additionalItemKeys.push( item.data );
	}

	function SaveTool() {
		SaveTool.super.apply( this, arguments );
	}
	OO.inheritClass( SaveTool, OO.ui.Tool );

	SaveTool.static.name = 'save';
	SaveTool.static.title = submitLabel;
	SaveTool.static.flags = [ 'primary', 'progressive' ];
	SaveTool.prototype.onSelect = function () {
		toolbar.emit( 'save' );
		this.setDisabled( true );
		return true;
	};
	SaveTool.prototype.onUpdateState = function () {};
	toolFactory.register( SaveTool );

	function CloseTool() {
		CloseTool.super.apply( this, arguments );
	}
	OO.inheritClass( CloseTool, OO.ui.Tool );

	CloseTool.static.name = 'close';
	CloseTool.static.title = mw.msg( 'wikifarm-button-action-label-cancel' );
	CloseTool.static.icon = 'close';
	CloseTool.prototype.onSelect = function () {
		toolbar.emit( 'close' );
		return true;
	};
	CloseTool.prototype.onUpdateState = function () {};
	toolFactory.register( CloseTool );

	toolbar.setup( [
		{
			type: 'bar',
			include: [ 'close' ]
		},
		{
			name: 'actions',
			classes: [ 'additional-actions' ],
			type: 'bar',
			include: additionalItemKeys
		},
		{
			name: 'actions',
			classes: [ 'default-actions' ],
			type: 'bar',
			include: [ 'save' ]
		}
	] );
	return toolbar;
};
