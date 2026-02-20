/**
 * When UTO loads the list, get the list of accessible wikis, and ask each wiki for its tasks,
 * then combine the results into a single list for UTO to display.
 */
mw.hook( 'UnifiedTaskOverview.getList' ).add( ( collector ) => {
	collector.addPromise(
		fetch( mw.util.wikiScript( 'rest' ) + '/bluespice/farm/v1/access/accessible' )
			.then( ( res ) => {
				if ( !res.ok ) {
					throw new Error( `Failed to fetch accessible wikis: ${ res.status } ${ res.statusText }` );
				}
				return res.json();
			} )
			.then( ( instances ) => {
				// build an array of per-wiki task fetch promises
				const perWiki = instances.map( ( instance ) => fetch( instance.url + '/rest.php/unifiedtaskoverview/list' )
					.then( ( res ) => {
						if ( !res.ok ) {
							throw new Error( `Failed to fetch tasks from ${ instance.url }: ${ res.status } ${ res.statusText }` );
						}
						return res.json();
					} )
					.then( ( tasks ) => {
						tasks.forEach( ( task ) => {
							task.source_wiki = instance; // eslint-disable-line camelcase
						} );
						return tasks;
					} )
				);

				// ignore failures across wikis, return only successful task arrays
				return Promise.all(
					perWiki.map( ( p ) =>
						Promise.resolve( p ) // eslint-disable-line implicit-arrow-linebreak
							.then( ( value ) => ( { status: 'fulfilled', value } ) )
							.catch( ( reason ) => ( { status: 'rejected', reason } ) )
					)
				).then( ( settled ) =>
					settled // eslint-disable-line implicit-arrow-linebreak
						.filter( ( r ) => r.status === 'fulfilled' )
						.reduce( ( acc, r ) => acc.concat( r.value ), [] )
				);
			} )
	);
} );
