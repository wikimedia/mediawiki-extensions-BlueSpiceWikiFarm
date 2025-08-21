ext.bluespiceWikiFarm.ui.EnhancedUploadParamsProcessor = function ( config ) {
	this.uploadToForeignCheck = new OO.ui.CheckboxInputWidget( {
		selected: false
	} );
	this.uploadToForeignCheckLayout = new OO.ui.FieldLayout( this.uploadToForeignCheck, {
		label: mw.msg( 'wikifarm-upload-file-foreign-repo-label' )
	} );
	this.config = config;
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.EnhancedUploadParamsProcessor, enhancedUpload.UiParamsProcessor );

ext.bluespiceWikiFarm.ui.EnhancedUploadParamsProcessor.prototype.getElement = function () {
	return this.uploadToForeignCheckLayout;
};

ext.bluespiceWikiFarm.ui.EnhancedUploadParamsProcessor.prototype.getParams = function ( params, item, skipOption ) { // eslint-disable-line no-unused-vars
	if ( this.uploadToForeignCheck.isSelected() && this.config.sharedWikiApiUrl ) {
		params.uploadToForeign = true;
		params.foreignUrl = this.config.sharedWikiApiUrl;
	} else {
		params.uploadToForeign = false;
	}
	return params;
};
