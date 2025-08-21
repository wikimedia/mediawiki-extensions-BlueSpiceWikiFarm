ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget = function ( cfg ) {
	ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget.parent.call( this, cfg );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget, ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget );

ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget.prototype.getUrl = function () {
	return mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/template-query-store';
};

ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget.prototype.getDataItemForOption = function ( dataItem ) {
	return {
		label: dataItem.display_title || dataItem.title,
		data: dataItem
	};
};

ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget.prototype.getSelectedTemplate = function () {
	const title = ext.bluespiceWikiFarm.ui.widget.SharedTemplateInputWidget.parent.prototype.getMWTitle.call( this );
	if ( title ) {
		return {
			title: title,
			_is_local_instance: this.selectedTitle._is_local_instance, // eslint-disable-line camelcase, no-underscore-dangle
			_instance: this.selectedTitle._instance // eslint-disable-line no-underscore-dangle
		};
	}
	return null;
};
