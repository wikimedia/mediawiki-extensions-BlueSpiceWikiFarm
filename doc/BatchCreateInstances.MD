# Batch creating instances

Instances can be created using a script

## Input file

Input file is JSON, where instance path is the key and options are value.

```json
{
    "my-instance": {
        "displayName": "My Instance",
        "lang": "de",
        "userName": "WikiSysop"
    },
    "my-instance2": {
        "displayName": "My Instance2",
        "lang": "en",
        "userName": "WikiSysop"
    }
}
```

Options for creation:

- `displayName` Display name of the instance
- `lang` Wiki language - must be a valid lang code
- `userName` User to create the wiki as - and copy it as "initial user" - must be a valid, existing user
- `metadata` Array of metadata items `{ "keywords": [ "kw1", "kw2" ], "group": "my-group" }`
- `config` Global variables to be set on the instance - do not specify unless really necessary
- `template` Template name to use for instance creation - see Templating system of wiki farm

## Execution

```php
php extensions/BlueSpiceWikiFarm/maintenance/BatchCreateInstances.php --file=/tmp/intances.json
```