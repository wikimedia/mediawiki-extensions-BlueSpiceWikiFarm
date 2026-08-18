const config = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle

if ( config.useSharedResources && config.sharedWikiApiUrl ) {
	const BookletLayout = bs.vec.ui.ForeignStructuredUpload.BookletLayout;
	const ForeignStructuredUpload = bs.vec.ui.ForeignStructuredUpload.ForeignStructuredUpload;

	if (
		BookletLayout &&
		ForeignStructuredUpload &&
		!BookletLayout.prototype.wikiFarmSharedUploadPatched
	) {
		const SHARED_UPLOAD_TARGET = 'farmsharedresources';
		const LOCAL_UPLOAD_TARGET = 'local';
		const originalRenderUploadForm = BookletLayout.prototype.renderUploadForm;
		const originalUploadFile = BookletLayout.prototype.uploadFile;
		const originalClear = BookletLayout.prototype.clear;

		BookletLayout.prototype.wikiFarmSharedUploadPatched = true;

		BookletLayout.prototype.makeWikiFarmUpload = function ( target ) {
			const upload = new ForeignStructuredUpload( target, {
				parameters: {
					errorformat: 'html',
					errorlang: mw.config.get( 'wgUserLanguage' ),
					errorsuselocal: 1,
					formatversion: 2
				}
			} );

			if ( target === SHARED_UPLOAD_TARGET ) {
				const foreignApi = new mw.ForeignApi( config.sharedWikiApiUrl );
				upload.api = foreignApi;
				upload.apiPromise = $.Deferred().resolve( foreignApi );
			}

			return upload;
		};

		BookletLayout.prototype.getSelectedUploadTarget = function () {
			if ( this.uploadToSharedCheck && this.uploadToSharedCheck.isSelected() ) {
				return SHARED_UPLOAD_TARGET;
			}
			return LOCAL_UPLOAD_TARGET;
		};

		BookletLayout.prototype.renderUploadForm = function () {
			const form = originalRenderUploadForm.apply( this, arguments );
			const items = form.getItems();
			if ( !items.length ) {
				return form;
			}

			this.uploadToSharedCheck = new OO.ui.CheckboxInputWidget( {
				selected: false
			} );
			this.uploadToSharedCheckLayout = new OO.ui.FieldLayout( this.uploadToSharedCheck, {
				align: 'inline',
				label: mw.msg( 'wikifarm-upload-file-foreign-repo-label' )
			} );

			items[ 0 ].addItems( [ this.uploadToSharedCheckLayout ] );
			return form;
		};

		BookletLayout.prototype.createUpload = function () {
			const selectedTarget = this.getSelectedUploadTarget();
			return this.makeWikiFarmUpload( selectedTarget );
		};

		BookletLayout.prototype.uploadFile = function () {
			const selectedTarget = this.getSelectedUploadTarget();
			this.target = selectedTarget;
			if ( this.upload && this.upload.target !== selectedTarget ) {
				this.upload = this.makeWikiFarmUpload( selectedTarget );
			}
			return originalUploadFile.apply( this, arguments );
		};

		BookletLayout.prototype.clear = function () {
			originalClear.apply( this, arguments );
			if ( this.uploadToSharedCheck ) {
				this.uploadToSharedCheck.setSelected( false );
			}
		};
	}
}
