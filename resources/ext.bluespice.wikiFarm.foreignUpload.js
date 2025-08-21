mw.hook( 'enhancedUpload.makeParamProcessor' ).add( ( paramsProcessor ) => {
	const config = ext.bluespiceWikiFarm._config(); // eslint-disable-line no-underscore-dangle
	if ( config.useSharedResources ) {
		paramsProcessor.processors.push( new ext.bluespiceWikiFarm.ui.EnhancedUploadParamsProcessor( config ) );
	}
} );
