const ButtonWithHelp = function ( config ) {
	config = config || {};
	ButtonWithHelp.super.call( this, config );
	if ( config.help ) {
		new OO.ui.LabelWidget(
			{
				label: config.help,
				classes: [ 'ext-bluespiceWikiFarm-button-with-label-help' ]
			}
		).$element.insertAfter( this.$label );
	}
};

OO.inheritClass( ButtonWithHelp, OO.ui.ButtonWidget );

ext.bluespiceWikiFarm.ui.TemplatePicker = function ( config ) {
	this.name = config.name || '';
	this.path = config.path || '';
	this.availableTemplates = config.availableTemplates || {};

	ext.bluespiceWikiFarm.ui.TemplatePicker.parent.call( this, {
		expanded: false,
		padded: false,
		classes: [ 'ext-bluespiceWikiFarm-extra-options-panel', 'col', 'col-sm-auto' ]
	} );
	this.render();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.TemplatePicker, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.TemplatePicker.prototype.render = function () {
	const blankBtn = new ButtonWithHelp( {
		data: 'blank',
		icon: 'templateAdd',
		label: mw.msg( 'wikifarm-template-blank' ),
		help: mw.msg( 'wikifarm-template-blank-help' )
	} );
	blankBtn.connect( this, { click: () => this.onItemClick( blankBtn ) } );

	const cloneBtn = new ButtonWithHelp( {
		data: 'template',
		icon: 'copy',
		label: mw.msg( 'wikifarm-template-clone' ),
		help: mw.msg( 'wikifarm-template-clone-help' )
	} );
	cloneBtn.connect( this, { click: () => this.onItemClick( cloneBtn ) } );

	this.$element.append( blankBtn.$element, cloneBtn.$element );

	if ( Object.keys( this.availableTemplates ).length ) {
		this.$element.append( new OO.ui.LabelWidget( {
			label: mw.msg( 'wikifarm-template-available-templates' ),
			classes: [ 'ext-bluespiceWikiFarm-template-picker-separator' ]
		} ).$element );
	}

	for ( const templateKey in this.availableTemplates ) {
		if ( this.availableTemplates.hasOwnProperty( templateKey ) ) {
			const template = this.availableTemplates[ templateKey ];
			const templateBtn = new ButtonWithHelp( {
				data: 'template/' + templateKey,
				label: template.label,
				help: template.description,
				classes: [ 'noicon' ]
			} );
			templateBtn.connect( this, { click: () => this.onItemClick( templateBtn ) } );
			this.$element.append( templateBtn.$element );
		}
	}
};

ext.bluespiceWikiFarm.ui.TemplatePicker.prototype.onItemClick = function ( item ) {
	const data = item.getData();
	if ( !data ) {
		return;
	}
	const bits = data.split( '/' );
	let selectedTemplate = null;
	if ( bits.length === 2 ) {
		selectedTemplate = bits[ 1 ];
	}

	const spName = mw.config.get( 'wgCanonicalSpecialPageName' );
	const title = mw.Title.makeTitle( -1, spName + '/_create/' + bits[ 0 ] );
	const params = {};
	if ( this.name ) {
		params.name = this.name;
	}
	if ( this.path ) {
		params.path = this.path;
	}
	if ( selectedTemplate ) {
		params.template = selectedTemplate;
	}

	window.location.href = title.getUrl( params );
};
