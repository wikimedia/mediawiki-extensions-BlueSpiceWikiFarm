# Dynamic groups

If wiki is set up to share users (`$wgWikiFarm_shareUsers = true;`), dynamic groups to facilitate basic rights
management can be used.

For each instance, following groups will be created:
- `<instance path>_reader` - read-only access
- `<instance path>_editor` - edit access
- `<instance path>_admin` - full access
- `<instance path>_reviewer` - QM

Since users are shared, users can be assigned to these groups in any instance.

Users with group `sysop` are excluded from dynamic groups, they can always access anything.

## Checking for user role on the wiki

To check if a user is in a specific group, use the following code:

```php

$control = MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.AccessControlStore' );
// $instance is an instance of InstanceEntity
$control->userHasRoleOnInstance( User::newFromName( 'WikiSysop' ), 'reader', $instance );

// or
$control->getInstancePathsWhereUserHasRole( User::newFromName( 'WikiSysop' ), 'reader' );
// [ 'Instance1', 'Instance2' ]

```
