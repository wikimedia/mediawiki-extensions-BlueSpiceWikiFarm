ext.bluespiceWikiFarm.ui.InstancePicker = function ( config ) {
	config = config || {};
	ext.bluespiceWikiFarm.ui.InstancePicker.parent.call( this, Object.assign( config, {
		queryAction: 'wikifarm-wiki-instance-store',
		labelField: 'title',
		groupBy: 'meta_group',
		groupLabelCallback: function ( group ) {
			return group || mw.message( 'wikifarm-group-undefined' ).plain();
		}
	} ) );
	this.$element.addClass( 'ext-bluespiceWikiFarm-instance-picker' );

};

OO.inheritClass( ext.bluespiceWikiFarm.ui.InstancePicker, OOJSPlus.ui.widget.StoreDataInputWidget );

ext.bluespiceWikiFarm.ui.InstancePicker.prototype.getLookupRequest = function () {
	const inputValue = this.value,
		queryData = Object.assign( {
			action: this.queryAction,
			limit: this.limit
		}, this.additionalQueryParams );

	queryData.filter = JSON.stringify( [ {
		comparison: 'eq',
		value: false,
		property: 'suspended',
		type: 'boolean'
	} ] );
	if ( inputValue.trim() !== '' ) {
		queryData.query = inputValue.trim();
	}

	return new mw.Api().get( queryData );
};

ext.bluespiceWikiFarm.ui.InstancePicker.prototype.selectFromPath = function ( path ) {
	new mw.Api().get( {
		action: this.queryAction,
		filter: JSON.stringify( [ {
			comparison: 'eq',
			value: path,
			property: 'path',
			type: 'string'
		} ] )
	} ).done( ( r ) => {
		if ( r.hasOwnProperty( 'results' ) && r.results.length === 1 ) {
			this.setValue( new OO.ui.MenuOptionWidget( {
				label: r.results[ 0 ].title,
				data: r.results[ 0 ]
			} ) );
		}
	} );
};
