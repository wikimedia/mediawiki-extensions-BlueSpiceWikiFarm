ext.bluespiceWikiFarm.ui.TemplatePicker = function ( config ) {
	this.name = config.name || '';
	this.template = config.template || 'blank';
	this.source = config.source || '';
	this.availableTemplates = config.availableTemplates || {};

	ext.bluespiceWikiFarm.ui.TemplatePicker.parent.call( this, {
		expanded: false,
		padded: true,
		classes: [ 'ext-bluespiceWikiFarm-extra-options-panel', 'col', 'col-sm-auto' ]
	} );
	this.render();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.TemplatePicker, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.TemplatePicker.prototype.render = function () {
	const items = [
		new buttonOptionWithHelp( { // eslint-disable-line new-cap, no-use-before-define
			data: '_blank',
			icon: 'templateAdd',
			label: mw.msg( 'wikifarm-template-blank' ),
			help: mw.msg( 'wikifarm-template-blank-help' )
		} ),
		new buttonOptionWithHelp( { // eslint-disable-line new-cap, no-use-before-define
			data: '_clone',
			icon: 'copy',
			label: mw.msg( 'wikifarm-template-clone' ),
			help: mw.msg( 'wikifarm-template-clone-help' )
		} )
	];

	for ( const templateKey in this.availableTemplates ) {
		if ( this.availableTemplates.hasOwnProperty( templateKey ) ) {
			const template = this.availableTemplates[ templateKey ];
			items.push( new buttonOptionWithHelp( { // eslint-disable-line new-cap, no-use-before-define
				data: templateKey,
				label: template.label,
				help: template.description
			} ) );
		}
	}
	this.picker = new OO.ui.ButtonSelectWidget( {
		items: items
	} );

	this.$element.append(
		new OO.ui.FieldsetLayout( {
			label: mw.msg( 'wikifarm-template-title' ),
			items: [ this.picker ]
		} ).$element
	);
};

var buttonOptionWithHelp = function ( config ) { // eslint-disable-line no-var
	config = config || {};
	buttonOptionWithHelp.super.call( this, config );
	if ( config.help ) {
		new OO.ui.LabelWidget(
			{
				label: config.help,
				classes: [ 'ext-bluespiceWikiFarm-button-with-label-help' ]
			}
		).$element.insertAfter( this.$label );
	}
};

OO.inheritClass( buttonOptionWithHelp, OO.ui.ButtonOptionWidget );
