# Sharing users and sessions

Farm can be configured in a way that users can log in to one wiki and be automatically logged in to
all other wikis in the farm. This is done by sharing user accounts and sessions between wikis.

There are two levels to this feature:

## Sharing user accounts

In this mode, all wikis in the farm share the same user accounts, user created on any of the instances, can log into
any other. But user still needs to log into each wiki separately.

Configuration
```php
$wgWikiFarmConfig_shareUsers = true;
$wgWikiFarmConfig_shareUserSessions = false;
```

Each wiki will have its own user session, which means that all cookies will be created separately for each instance.
Cookies of each instance will have their own `path` and `prefix` to avoid conflicts.

## Sharing user sessions

In this mode, all wikis in the farm share the same user accounts and user sessions. This means that when a user logs in
to one wiki, they are automatically logged in to all other wikis in the farm.

In order to share the sessions, farm MUST also share user accounts.

Configuration
```php
$wgWikiFarmConfig_shareUsers = true;
$wgWikiFarmConfig_shareUserSessions = true;
```

In this mode, all cookies will be created with the same `path` and `prefix` so they can be re-used across all wikis.
This also applies to non-session cookies, ie. if user accepts cookies on one wiki, they will be accepted on all wikis.

## Notes
- IMPORTANT: Configuration must be set in `LocalSettings.php` before the farm is loaded, ie

```php
$wgWikiFarmConfig_shareUsers = true;
$wgWikiFarmConfig_shareUserSessions = true;
require_once "$IP/extensions/BlueSpiceWikiFarm/BlueSpiceWikiFarm.php";
```

- Its recommended to set this on fresh installs of the farm, as it simplifies the isssues of merging users.
If you want to set it up on existing farm, use `SyncUsers.php` script to merge users.

## Advanced configuration

By default, main wiki (usually `w`) DB will be used as storage for user tables and sessions. If you want to use
a different DB for user tables and sessions, you can configure it like this:

```php
$wgWikiFarmConfig_sharedUserDBname = 'my_shared_user_db';
$wgWikiFarmConfig_sharedUserDBprefix = 'msud_'; // Optional - defaults to `''`
```
