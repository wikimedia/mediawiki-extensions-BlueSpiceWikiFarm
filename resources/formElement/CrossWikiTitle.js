const config = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle
if ( config.useUnifiedSearch && config.useGlobalAccessControl ) {
	ext.bluespiceWikiFarm.formElement.CrossWikiTitle = function () {
		ext.bluespiceWikiFarm.formElement.CrossWikiTitle.super.call( this );
	};

	OO.inheritClass( ext.bluespiceWikiFarm.formElement.CrossWikiTitle, mw.ext.forms.formElement.InputFormElement );

	ext.bluespiceWikiFarm.formElement.CrossWikiTitle.prototype.getWidgets = function () {
		return {
			view: mw.ext.forms.widget.view.TextView,
			edit: ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget
		};
	};

	mw.ext.forms.registry.Type.register( 'title', new ext.bluespiceWikiFarm.formElement.CrossWikiTitle() );
}
