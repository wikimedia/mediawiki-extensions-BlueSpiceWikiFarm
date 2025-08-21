mw.hook( 'bs.extendedSearch.ToolsPanel.addFilters' ).add( ( filters, tools, lookup ) => {
	const context = lookup.context || null;
	if ( context && context.key !== 'farm-global' ) {
		// Do not render if context is set to something else
		return;
	}
	const instancePicker = new ext.bluespiceWikiFarm.ui.widget.SearchInstanceSelector( {
		options: mw.config.get( 'BSWikiFarmSearchInstances' ) || [],
		context: context,
		lookup: lookup
	} );
	tools.$filtersContainer.append( instancePicker.$element );
} );
