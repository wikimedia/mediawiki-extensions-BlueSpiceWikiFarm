ext.bluespiceWikiFarm.ui.InstanceSourcePanel = function ( config ) {
	config = config || {};

	// Take-over values
	this.name = config.name || '';
	this.path = config.path || '';

	this.availableTemplates = config.templates || {};

	ext.bluespiceWikiFarm.ui.InstanceSourcePanel.parent.call( this, { expanded: false, padded: false } );
	this.render();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.InstanceSourcePanel, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.InstanceSourcePanel.prototype.render = function () {
	this.templatePicker = new ext.bluespiceWikiFarm.ui.TemplatePicker( {
		availableTemplates: this.availableTemplates,
		path: this.path,
		name: this.name
	} );
	this.$element.append( this.templatePicker.$element );
};
