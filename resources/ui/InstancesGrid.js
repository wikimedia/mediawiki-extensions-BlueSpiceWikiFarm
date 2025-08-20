ext.bluespiceWikiFarm.ui.InstancesGrid = function ( config ) { // eslint-disable-line no-unused-vars

	this.store = new OOJSPlus.ui.data.store.RemoteStore( {
		action: 'wikifarm-wiki-instance-store',
		pageSize: 20,
		groupField: 'meta_group',
		sorter: {
			meta_group: { // eslint-disable-line camelcase
				direction: 'ASC'
			}
		}
	} );

	ext.bluespiceWikiFarm.ui.InstancesGrid.parent.call( this, {
		deletable: false,
		columns: {
			path: {
				headerText: mw.message( 'wikifarm-instance-title' ).plain(),
				type: 'text',
				sortable: true,
				filter: {
					type: 'text'
				},
				valueParser: function ( value, row ) {
					if ( row.suspended ) {
						return new OO.ui.HtmlSnippet( mw.html.element(
							's',
							{
								class: 'wikifarm-suspended'
							},
							row.title
						) );
					}
					if ( !row.is_complete ) {
						return new OO.ui.HtmlSnippet( $( '<span>' ).css( {
							color: 'grey'
						} ).attr( 'title', mw.msg( 'wikifarm-incomplete-instance' ) ).text( row.title ) );
					}
					return new OO.ui.HtmlSnippet( mw.html.element(
						'a',
						{
							href: row.fullurl,
							class: 'external',
							target: '_blank'
						},
						row.title
					) );
				}
			},
			notsearchable: {
				headerText: mw.message( 'wikifarm-instance-notsearchable' ).plain(),
				type: 'boolean',
				sortable: true,
				filter: {
					type: 'boolean'
				},
				valueParser: function ( value ) {
					return !value;
				}
			},
			ctime: {
				headerText: mw.message( 'wikifarm-instance-ctime' ).plain(),
				type: 'text',
				sortable: true,
				filter: {
					type: 'date'
				}
			},
			meta_keywords: { // eslint-disable-line camelcase
				headerText: mw.message( 'wikifarm-instance-keywords' ).plain(),
				type: 'text',
				sortable: true,
				filter: { type: 'text' },
				valueParser: function ( value ) {
					if ( typeof value === 'object' ) {
						return value.join( ', ' );
					}
					return value;
				}
			},
			meta_desc: { // eslint-disable-line camelcase
				headerText: mw.message( 'wikifarm-instance-desc' ).plain(),
				type: 'text',
				sortable: false,
				filter: { type: 'text' }
			}
		},
		store: this.store
	} );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.InstancesGrid, OOJSPlus.ui.data.GridWidget );

ext.bluespiceWikiFarm.ui.InstancesGrid.prototype.getStore = function () {
	return this.store;
};
