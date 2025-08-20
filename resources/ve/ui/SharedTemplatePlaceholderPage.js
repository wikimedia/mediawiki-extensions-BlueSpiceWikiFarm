ext.bluespiceWikiFarm.ve.SharedTemplatePlaceholderPage = function ( placeholder, name, config ) {

	// Configuration initialization
	config = ve.extendObject( {
		scrollable: false
	}, config );

	// Parent constructor
	ve.ui.MWTemplatePlaceholderPage.super.call( this, name, config );

	// Properties
	this.placeholder = placeholder;

	this.addTemplateInput = new ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget( {
		$overlay: config.$overlay
	} ).connect( this, {
		change: 'onTemplateInputChange',
		enter: 'onAddTemplate'
	} );

	this.addTemplateInput.getLookupMenu().connect( this, {
		choose: 'onAddTemplate'
	} );

	this.addTemplateButton = new OO.ui.ButtonWidget( {
		label: ve.msg( 'visualeditor-dialog-transclusion-add-template-save' ),
		flags: [ 'progressive' ],
		classes: [ 've-ui-mwTransclusionDialog-addButton' ],
		disabled: true
	} )
		.connect( this, { click: 'onAddTemplate' } );

	const addTemplateActionFieldLayout = new OO.ui.ActionFieldLayout(
		this.addTemplateInput,
		this.addTemplateButton,
		{
			label: ve.msg( 'visualeditor-dialog-transclusion-template-search-help' ),
			align: 'top'
		}
	);

	const dialogTitle = this.placeholder.getTransclusion().isSingleTemplate() ?
		'visualeditor-dialog-transclusion-template-search' :
		'visualeditor-dialog-transclusion-add-template';

	const addTemplateFieldsetConfig = {
		// The following messages are used here:
		// * visualeditor-dialog-transclusion-template-search
		// * visualeditor-dialog-transclusion-add-template
		label: ve.msg( dialogTitle ),
		icon: 'puzzle',
		classes: [ 've-ui-mwTransclusionDialog-addTemplateFieldset' ],
		items: [ addTemplateActionFieldLayout ]
	};

	this.addTemplateFieldset = new OO.ui.FieldsetLayout( addTemplateFieldsetConfig );

	// Initialization
	this.$element
		.addClass( 've-ui-mwTemplatePlaceholderPage' )
		.append( this.addTemplateFieldset.$element );
};

OO.inheritClass( ext.bluespiceWikiFarm.ve.SharedTemplatePlaceholderPage, ve.ui.MWTemplatePlaceholderPage );
