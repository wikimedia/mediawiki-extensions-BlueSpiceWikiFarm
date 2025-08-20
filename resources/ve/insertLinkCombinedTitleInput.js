const config = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle
if ( config.useUnifiedSearch && config.useGlobalAccessControl ) {
	bs.vec.registerComponentPlugin(
		bs.vec.components.LINK_ANNOTATION_INSPECTOR,
		( component ) => {
			component.linkTypeIndex.addTabPanels( [
				new OO.ui.TabPanelLayout( 'internal', {
					label: 'Pages', // ve.msg( 'bs-visualeditorconnector-tab-file' ),
					expanded: false,
					scrollable: false,
					padded: true
				} ),
				component.linkTypeIndex.getTabPanel( 'external' )
			] );

			component.combinedTitleInput =
				new ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget();

			component.linkTypeIndex.getTabPanel( 'internal' ).$element.append(
				component.combinedTitleInput.$element
			);
			component.combinedTitleInput.connect( component, { change: function () {
				this.updateActions();
			} } );
			component.annotationInput = component.combinedTitleInput;

			return {
				updateActions: function () {
					const inputWidget = this.combinedTitleInput;
					if (
						!inputWidget ||
						!inputWidget.getAnnotation() ||
						!( inputWidget.getAnnotation() instanceof ve.dm.MWInternalLinkAnnotation )
					) {
						return true;
					}
					this.actions.forEach( { actions: [ 'done', 'insert' ] }, ( action ) => {
						action.setDisabled( !inputWidget.internalPicker.getTitleObject() );
					} );
					return false;
				}
			};
		}
	);
}
