ext.bluespiceWikiFarm.ui.widget.AdminUserGroupMultiselectWidget = function ( config ) {
	ext.bluespiceWikiFarm.ui.widget.AdminUserGroupMultiselectWidget.super.call( this, config );
};

OO.inheritClass(
	ext.bluespiceWikiFarm.ui.widget.AdminUserGroupMultiselectWidget,
	OOJSPlus.ui.widget.UserGroupMultiselectWidget
);

ext.bluespiceWikiFarm.ui.widget.AdminUserGroupMultiselectWidget.prototype.getUserQueryRequest = function ( inputValue, userFilters ) {
	return this.queryStore( 'users', {
		query: inputValue,
		filter: JSON.stringify( userFilters ),
		limit: inputValue !== '' ? 10 : 5
	} );
};

ext.bluespiceWikiFarm.ui.widget.AdminUserGroupMultiselectWidget.prototype.getGroupQueryResult = function ( inputValue, groupFilters ) {
	return this.queryStore( 'groups', {
		query: inputValue,
		filter: JSON.stringify( groupFilters ),
		allowEveryone: this.allowEveryoneOption
	} );
};

ext.bluespiceWikiFarm.ui.widget.AdminUserGroupMultiselectWidget.prototype.queryStore = function ( type, params ) {
	const dfd = $.Deferred();
	const req = $.ajax( {
		method: 'GET',
		url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/access/assignable/' + type,
		data: params
	} ).done( ( response ) => {
		if ( response && response.results ) {
			dfd.resolve( response.results );
			return;
		}
		dfd.resolve( [] );
	} ).fail( ( err ) => {
		dfd.resolve( err );
	} );
	return dfd.promise( { abort: function () {
		req.abort();
	} } );
};
