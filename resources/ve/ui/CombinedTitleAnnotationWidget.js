ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget = function () {
	ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.super.apply( this, arguments );
	this.addOtherTools();
	this.input.$element.hide();
};

OO.inheritClass( ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget, ve.ui.MWInternalLinkAnnotationWidget );

ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.prototype.onTextChange = function ( value ) { // eslint-disable-line no-unused-vars
	if ( this.internalPicker.getTitleObject() ) {
		this.setAnnotation(
			this.constructor.static.getAnnotationFromText( this.internalPicker.getTitleObject(), true ), true
		);
	} else {
		this.setAnnotation( null, true );
	}
};

ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.prototype.addOtherTools = function () {
	this.internalPicker = new ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget( {
		$overlay: true
	} );
	this.internalPicker.connect( this, {
		change: 'onTextChange',
		choose: 'onTextChange'
	} );

	this.$element.append( new OO.ui.FieldsetLayout( {
		items: [
			this.internalPicker
		]
	} ).$element );
};

ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.static.getAnnotationFromText = function ( title ) {
	if ( title ) {
		if ( title._is_local_instance ) { // eslint-disable-line no-underscore-dangle
			return ve.dm.MWInternalLinkAnnotation.static.newFromTitle( mw.Title.newFromText( title.prefixed ) );
		}
		const target = title._instance_interwiki + ':' + title.prefixed; // eslint-disable-line no-underscore-dangle
		return new ve.dm.MWInternalLinkAnnotation( {
			type: 'link/mwInternal',
			attributes: {
				title: target,
				normalizedTitle: target,
				lookupTitle: title.prefixed
			}
		} );
	}
	return null;
};

ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.prototype.getTextInputWidget = function () {
	if ( !this.internalPicker ) {
		return ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.parent.prototype.getTextInputWidget.call( this );
	}
	return this.internalPicker;
};

ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.prototype.setAnnotation = function ( annotation, fromText ) {
	ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.parent.prototype.setAnnotation.call( this, annotation, fromText );
	if ( !this.annotation ) {
		return;
	}
	if ( !fromText ) {
		this.internalPicker.setValue( this.constructor.static.getTextFromAnnotation( this.annotation ) );
	}

	return this;
};

ext.bluespiceWikiFarm.ui.CombinedTitleAnnotationWidget.static.getTextFromAnnotation = function ( annotation ) {
	if ( !annotation ) {
		return '';
	}

	return annotation.element.attributes.lookupTitle;
};
