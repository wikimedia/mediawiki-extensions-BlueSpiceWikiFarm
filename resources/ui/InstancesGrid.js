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

	const columns = {
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

				const $anchor = $( '<a>' ).attr( {
					href: row.fullurl,
					class: 'external',
					target: '_blank'
				} ).text( row.title );
				const $color = $( '<div>' ).addClass( 'instance-color-indicator' );
				$color.css( {
					width: '5px',
					height: '15px',
					'margin-right': '4px',
					display: 'inline-block',
					'border-radius': '2px'
				} );

				if ( row.instance_color ) {
					$color.css( 'background-color', row.instance_color.background );
				}
				return new OO.ui.HtmlSnippet( $( '<div>' ).append( $color, $anchor ).html() );
			}
		}
	};

	if ( !ext.bluespiceWikiFarm._config().useUnifiedSearch ) { // eslint-disable-line no-underscore-dangle
		columns.notsearchable = {
			headerText: mw.message( 'wikifarm-instance-notsearchable' ).plain(),
			type: 'boolean',
			sortable: true,
			filter: {
				type: 'boolean'
			},
			valueParser: function ( value ) {
				return !value;
			}
		};
	}
	columns.ctime = {
		headerText: mw.message( 'wikifarm-instance-ctime' ).plain(),
		type: 'text',
		sortable: true,
		filter: {
			type: 'date'
		}
	};
	columns.meta_keywords = { // eslint-disable-line camelcase
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
	};
	columns.meta_desc = { // eslint-disable-line camelcase
		headerText: mw.message( 'wikifarm-instance-desc' ).plain(),
		type: 'text',
		sortable: false,
		filter: { type: 'text' }
	};

	ext.bluespiceWikiFarm.ui.InstancesGrid.parent.call( this, {
		deletable: false,
		columns: columns,
		store: this.store
	} );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.InstancesGrid, OOJSPlus.ui.data.GridWidget );

ext.bluespiceWikiFarm.ui.InstancesGrid.prototype.getStore = function () {
	return this.store;
};
