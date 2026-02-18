 Extensions usually have access to a wiki ID (WikiMap::getCurrentWikiId()).
 In order to get the wiki instance information, you can fire following hooks

## Getting Interwiki prefix for Title construction 
```php
GetInterwikiPrefixFromWikiId( string $wikiId, string &$interwikiPrefix )
```

## Get info about the instance
```php
GetWikiInfoFromWikiId( string $wikiId, array &$wikiInfo )
// Will return (at least):
// - display_text: the display name of the wiki instance
// - url: the URL of the wiki instance
```
