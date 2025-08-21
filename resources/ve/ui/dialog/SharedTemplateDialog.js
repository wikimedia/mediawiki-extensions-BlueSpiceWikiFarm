ext.bluespiceWikiFarm.ve.dialog.SharedTemplateDialog = function ( config ) {
	ext.bluespiceWikiFarm.ve.dialog.SharedTemplateDialog.super.call( this, config );
	// Look into ve.ui.MWTemplateDialog.getSetupProcess
};

OO.inheritClass( ext.bluespiceWikiFarm.ve.dialog.SharedTemplateDialog, ve.ui.MWTransclusionDialog );

ext.bluespiceWikiFarm.ve.dialog.SharedTemplateDialog.prototype.getPageFromPart = function ( part ) {
	if ( part instanceof ve.dm.MWTemplateModel ) {
		return new ve.ui.MWTemplatePage( part, part.getId(), { $overlay: this.$overlay, isReadOnly: this.isReadOnly() } );
	} else if ( part instanceof ve.dm.MWTemplatePlaceholderModel ) {
		return new ext.bluespiceWikiFarm.ve.SharedTemplatePlaceholderPage(
			part,
			part.getId(),
			{ $overlay: this.$overlay }
		);
	}
	return null;
};
