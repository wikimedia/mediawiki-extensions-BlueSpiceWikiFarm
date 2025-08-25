ext.bluespiceWikiFarm.ui.ManagementPanel = function ( config ) { // eslint-disable-line no-unused-vars
	ext.bluespiceWikiFarm.ui.ManagementPanel.parent.call( this, {
		expanded: false,
		padded: true
	} );

	this.selectedRow = null;
	this.canAdd = true;
	this.limits = null;

	this.$noticeCnt = $( '<div>' ).css( 'margin-bottom', '20px' );
	this.$element.append( this.$noticeCnt );
	this.makeGrid();
	this.makeActions();
	this.evaluateLimits();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.ManagementPanel, OO.ui.PanelLayout );

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.evaluateLimits = function () {
	$.ajax( { url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/limits' } ).done(
		( data ) => {
			if ( !data ) {
				return;
			}
			if ( !data.limited ) {
				return;
			}
			this.canAdd = data.active < data.limit;
			this.limits = data;
			this.addLimitBanner();
			if ( !this.buttons || this.canAdd ) {
				return;
			}
			for ( let i = 0; i < this.buttons.length; i++ ) {
				const btnData = this.buttons[ i ].getData();
				if ( btnData.hasOwnProperty( 'mustBeAbleToAdd' ) && btnData.mustBeAbleToAdd ) {
					this.buttons[ i ].setDisabled( true );
				}
			}
		}
	);
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.addLimitBanner = function () {
	this.$noticeCnt.children().remove();
	if ( !this.canAdd ) {
		this.$noticeCnt.prepend( new OO.ui.MessageWidget( {
			type: 'warning',
			label: mw.message( 'wikifarm-error-instance-limit-reached', this.limits.limit ).text()
		} ).$element );

	} else {
		this.$noticeCnt.prepend( new OO.ui.MessageWidget( {
			type: 'info',
			label: mw.message( 'wikifarm-instance-limit-banner', this.limits.active, this.limits.limit ).text()
		} ).$element );
	}
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.makeGrid = function () {
	this.grid = new ext.bluespiceWikiFarm.ui.InstancesGrid( {} );

	this.grid.getStore().connect( this, {
		load: 'setAbilities'
	} );
	this.grid.connect( this, {
		rowSelected: 'onRowSelected',
		datasetChange: 'onRowSelected'
	} );

	this.$element.append( this.grid.$element );
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.makeActions = function () {
	this.createButton = new OO.ui.ButtonWidget( {
		icon: 'add',
		label: mw.message( 'wikifarm-create-instance' ).plain(),
		flags: [ 'primary', 'progressive' ],
		href: this.getActionUrl( '_create' ),
		data: {
			action: 'create',
			mustBeSelected: false,
			mustBeAbleToAdd: true,
			mustBeComplete: false
		}
	} );
	this.cloneButton = new OO.ui.ButtonWidget( {
		icon: 'articleDisambiguation',
		label: mw.message( 'wikifarm-clone-instance' ).plain(),
		flags: [ 'progressive' ],
		href: this.getActionUrl( 'create' ),
		data: {
			action: 'clone',
			mustBeSelected: true,
			mustBeAbleToAdd: true
		}
	} );
	this.suspendButton = new OO.ui.ButtonWidget( {
		icon: 'pause',
		label: mw.message( 'wikifarm-suspend-instance' ).plain(),
		data: {
			action: 'suspend',
			mustBeSelected: true,
			visibilityCallback: function ( row ) {
				return row && !row.suspended && !row.is_system;
			}
		}
	} );
	this.deleteButton = new OO.ui.ButtonWidget( {
		icon: 'trash',
		label: mw.message( 'wikifarm-button-action-label-delete' ).plain(),
		flags: [ 'primary', 'destructive' ],
		data: {
			action: 'remove',
			mustBeSelected: true,
			mustBeComplete: false,
			visibilityCallback: function ( row ) {
				return row && !row.is_system;
			}
		}
	} );
	this.resumeButton = new OO.ui.ButtonWidget( {
		icon: 'play',
		label: mw.message( 'wikifarm-resume-instance' ).plain(),
		data: {
			action: 'resume',
			mustBeSelected: true,
			mustBeAbleToAdd: true,
			visibilityCallback: function ( row ) {
				return row && !!row.suspended;
			}
		}
	} );
	this.editButton = new OO.ui.ButtonWidget( {
		icon: 'edit',
		label: mw.message( 'wikifarm-edit-instance' ).plain(),
		data: {
			action: 'edit',
			mustBeSelected: true,
			visibilityCallback: function ( row ) {
				return row && !row.is_system;
			}
		}
	} );

	this.buttons = [
		this.createButton, this.cloneButton, this.editButton,
		this.suspendButton, this.resumeButton, this.deleteButton
	];

	const me = this;
	for ( let i = 0; i < this.buttons.length; i++ ) {
		this.buttons[ i ].connect( this.buttons[ i ], {
			click: function () {
				me.onButtonClick( this.getData() );
			}
		} );
		if ( this.buttons[ i ].getData().mustBeSelected ) {
			this.buttons[ i ].setDisabled( true );
		}
	}

	const panel = new OO.ui.HorizontalLayout( { items: this.buttons } );
	panel.$element.css( 'margin-bottom', '20px' );
	panel.$element.insertBefore( this.grid.$element );

	this.setAbilities();
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.onButtonClick = function ( data ) {
	const action = data.action;
	if ( action === 'create' ) {
		this.redirect( '_create' );
		return;
	}
	if ( action === 'clone' ) {
		this.redirect( '_create', { template: 'clone', source: this.selectedRow.path } );
		return;
	}
	if ( action === 'edit' ) {
		this.redirect( this.selectedRow.path );
		return;
	}
	this.openTaskDialog( action, this.selectedRow );
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.redirect = function ( action, params ) {
	params = params || {};
	window.location.href = this.getActionUrl( action, params );
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.getActionUrl = function ( action, params ) {
	const title = mw.Title.makeTitle( -1, mw.config.get( 'wgCanonicalSpecialPageName' ) + '/' + action );
	return title.getUrl( params );
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.onRowSelected = function ( data ) {
	if ( !this.buttons ) {
		return;
	}
	if ( data && data.hasOwnProperty( 'item' ) ) {
		this.selectedRow = data.item;
	} else {
		this.selectedRow = null;
	}
	this.setAbilities();
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.setAbilities = function () {
	if ( !this.buttons ) {
		return;
	}
	for ( let i = 0; i < this.buttons.length; i++ ) {
		const btnData = this.buttons[ i ].getData();
		this.buttons[ i ].$element.show();
		this.buttons[ i ].setDisabled( false );
		if ( btnData.mustBeSelected ) {
			this.buttons[ i ].setDisabled( !this.selectedRow );
		}
		if ( !this.canAdd && btnData.hasOwnProperty( 'mustBeAbleToAdd' ) && btnData.mustBeAbleToAdd ) {
			this.buttons[ i ].setDisabled( true );
		}
		if ( btnData.hasOwnProperty( 'visibilityCallback' ) ) {
			if ( btnData.visibilityCallback( this.selectedRow ) ) {
				this.buttons[ i ].$element.show();
			} else {
				this.buttons[ i ].$element.hide();
			}
		}
		const mustBeComplete = btnData.mustBeComplete === undefined ? true : btnData.mustBeComplete;
		if ( this.selectedRow && mustBeComplete ) {
			this.buttons[ i ].setDisabled( !this.selectedRow.is_complete );
		}
	}
};

ext.bluespiceWikiFarm.ui.ManagementPanel.prototype.openTaskDialog = function ( action, item ) {
	let dialog;
	switch ( action ) {
		case 'remove':
			dialog = new ext.bluespiceWikiFarm.ui.dialog.RemoveInstanceDialog( { path: item.path } );
			break;
		case 'suspend':
			dialog = new ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog( {
				path: item.path,
				action: 'suspend',
				title: mw.message( 'wikifarm-suspend-instance' ).plain(),
				prompt: mw.message( 'wikifarm-suspend-prompt' ).plain()
			} );
			break;
		case 'resume':
			dialog = new ext.bluespiceWikiFarm.ui.dialog.ConfirmDialog( {
				path: item.path,
				action: 'resume',
				title: mw.message( 'wikifarm-resume-instance' ).plain(),
				prompt: mw.message( 'wikifarm-resume-prompt' ).plain()
			} );
			break;
	}

	if ( dialog ) {
		const windowManager = new OO.ui.WindowManager();
		$( document.body ).append( windowManager.$element );
		windowManager.addWindows( [ dialog ] );
		windowManager.openWindow( dialog ).closed.then( ( res ) => {
			$( document.body ).remove( windowManager.$element );
			if ( res && res.needsReload ) {
				this.grid.getStore().reload();
				this.evaluateLimits();
			}
		} );
	}
};
