ext.bluespiceWikiFarm.ui.PathInput = function ( config ) {
	config = config || {};
	config.name = 'path';
	config.required = true;
	ext.bluespiceWikiFarm.ui.PathInput.parent.call( this, config );
	OO.ui.mixin.PendingElement.call( this, {} );
	this.$element.addClass( 'ext-bluespiceWikiFarm-path-input' );

	this.shouldCheckValidity = true;
	this.timeout = null;
	this.ajax = null;
	this.connect( this, {
		change: 'onInputChange'
	} );
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.PathInput, OO.ui.TextInputWidget );
OO.mixinClass( ext.bluespiceWikiFarm.ui.PathInput, OO.ui.mixin.PendingElement );

ext.bluespiceWikiFarm.ui.PathInput.prototype.clear = function () {
	this.setCheckValidity( false );
	this.popPending();
	this.setValue( '' );
	this.setDisabled( false );
	if ( this.ajax ) {
		this.ajax.abort();
	}
	this.setCheckValidity( true );
};

ext.bluespiceWikiFarm.ui.PathInput.prototype.onInputChange = function ( value ) {
	if ( this.ajax ) {
		this.ajax.abort();
	}
	if ( !this.shouldCheckValidity ) {
		// When value is set by the path generator, no need to check validity
		return;
	}
	// Wait for user to finish typing
	if ( this.timeout ) {
		clearTimeout( this.timeout );
	}
	this.timeout = setTimeout( () => {
		value = value.trim();
		if ( !value ) {
			this.setValidityFlag( false );
			return;
		}
		this.pushPending();
		this.getValidity().done( () => {
			this.setValidityFlag( true );
		} ).fail( () => {
			this.setValidityFlag( false );
		} );
	}, 500 );
};

ext.bluespiceWikiFarm.ui.PathInput.prototype.getValidity = function () {
	// Make ajax call, but first cancel any ongoing call
	if ( this.ajax ) {
		this.ajax.abort();
	}
	const dfd = $.Deferred();
	this.ajax = $.ajax( {
		url: mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/check_path_validity',
		data: {
			path: this.getValue()
		}
	} );
	this.ajax.done( () => {
		dfd.resolve();
	} ).fail( () => {
		dfd.reject();
	} );

	return dfd.promise();
};

ext.bluespiceWikiFarm.ui.PathInput.prototype.setValidityFlag = function ( valid ) {
	ext.bluespiceWikiFarm.ui.PathInput.parent.prototype.setValidityFlag.call( this, valid );
	this.popPending();
	this.emit( 'validityChange', valid );
};

ext.bluespiceWikiFarm.ui.PathInput.prototype.setCheckValidity = function ( doCheck ) {
	this.shouldCheckValidity = doCheck;
};
