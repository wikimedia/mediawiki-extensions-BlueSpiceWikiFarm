# Shared resources

Enable: `$GLOBALS['wgWikiFarmConfig_useSharedResources'] = true;`

## Shared files

Assumes: Existence of `-shared` instance.

A new `wgForeignFileRepos` is added, that points to the `-shared` instance.
This is done in `maybeSetupSharedResources` or `Dispatcher`

Note: ForeignFileRepos relies on all involved instances to be on the same lang or that at least shared instances 
is in EN. This is becuase foreign files
will be included in the page using the NS_FILE name in the language of the shared instance, and if the local instance
is in different language, it will not be able to find the file. 
To mitigate this, we set the file namespace to the canonical namespace name for shared resources wiki, on `APIAfterExecute`

## Shared templates

### VE integration

`ext.bluespiceWikiFarm.ve.dialog.SharedTemplateDialog` class overrides standard VE transclusion dialog,
to allow our "combined template" input to be added. Searching there will call
`bluespice/farm/v1/template-query-store` endpoint to get templates from local wiki and the `-shared` instance.

Once selected, as far as VE is concerned, it will be treated as a regular template.

### Backend integration

1. Loading template data for inclusion

Getting template data for a shared template. This is called from VE, as part of normal template inclusion process,
but, in backend, we intercept call to `APIAfterExecute` hook, and as long as template doesnt exist locally,
but exists in shared instance, we will fetch it from the `-shared` instance and return it.

2. Displaying template - transcluding

When parser tries to fetch a template, it will first check if it exists locally.
If it does, it will be used. If it does not, we will check the `-shared` instance.
It template exists on the `-shared` instance, its content will be fetched and a "virtual" Revision object will be 
created and returned to the parser. From parsers POV, it will be as if the template was local.
Done in `BeforeParserFetchTemplateRevisionRecord` hook.

 