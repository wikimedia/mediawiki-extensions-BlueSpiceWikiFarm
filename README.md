# Setup

    <documentroot>/w/
                     _sf_instances/
                                   <instance name>/
                                                   images/
                                                   extensions/BlueSpiceFoundation/config
                                                                                 /data
                                                   LocalSettings.custom.php <- Included _after_ LocalSettings.BlueSpice.php
                    LocalSettings.BlueSpice.php
                  /.htaccess

# Executing CLI scripts ...
## ... for a single instance
You can just add the extra argument `sfr` to every MediaWiki maintenance script. An example:

    php maintenance/dumpBackup.php --current --sfr "Some Wiki Instance"

sometimes --sfr parameter cannot be placed at the end. In those cases put it in the beginning:

    php maintenance/createAndPromote.php --sfr=TestNew --sysop NewOne test1234


## ... for all instances
There is a dedicated wrapper script `RunForAll` available. An example:

    php extensions/BlueSpiceWikiFarm/maintenance/RunForAll.php --script "maintenance/update.php" --args "--quick"

## Sharing one database
Farm can be set up to use one database for all instances. Every instance will be assigned a different table prefix in that case.

    $wgWikiFarmConfig_useSharedDB = true;
    $wgWikiFarmConfig_sharedDBname = 'my_shared_db'; // Defaults to "management" DB (w)

# Setup on Windows
Set correct PHP command:

    $wgPhpCli = "C:\path\to\php\php.exe"
or, if PHP is added to PATH, just:

    $wgPhpCli = "php";

Make sure temp folder is writable by the webserver. If default temp folder cannot be made writable,
change temp folder path ini `php.ini`:

    sys_temp_dir = "C:\writable\dir\"

Make sure that PHP extension `php_pdo_mysql.dll` is activated.

# Useful commands
## Putting instance in maintenance mode manually
In case of planned maintenance, migration preparation and similar, it may be needed to put the instance in maintenance
mode manually, to prevent usage

    $manager = \MediaWiki\MediaWikiServices::getInstance()->getService( 'BlueSpiceWikiFarm.InstanceManager' );
    $instance = $manager->getStore()->getInstanceByIdOrPath( 'myinstance' );
    // Set
    $manager->putInstanceInMaintenanceMode( $instance, 'Custom maintenance message' );
    // Clear
    $manager->clearMaintenanceMode( $instance );

## Creating instance shell for migration

In case of migration, you can create a shell instance, which will be used to migrate the data from the old instance

    php extensions/BlueSpiceWikiFarm/maintenance/CreateInstanceShell.php --path "myinstance" --displayName "My instance" --lang=de

After running this, database needs to be created manually, and instance directory needs to be created.
All of the data for this will be printed to the console.

## Querying titles across all searchable instances

RestAPI: http://domain/AnyInstance/rest.php/bluespice/farm/v1/combined-title-query-store?filter=[{"type":"boolean","value":true,"operator":"eq","property":"is_content_page"}]&limit=10&query=Test
Will return all pages matching `Test` in title, across all instances that are searchable

Widget: `ext.bluespiceWikiFarm.ui.widget.CombinedTitleInputWidget`, use `widget.getTitleKeyForLinking()` to get
the title text for linking to the page. If result not from local instance, interwiki prefix will be prepended

## Executing general queries across all instances
Section above working using `BlueSpiceWikiFarm.GlobalDatabaseQuery` service.

You can call it to execute any select query on every active and searchable instance.

Use it as normal DB connection (`$globalDatabaseQuery->select( 'table', $fields, $conds, $options, $join_conds )`) and it will
execute the query on every instance and return the results.

Results will be returned as usual, in a `ResultWrapper`, but each result will have some special fields
- `_instance` => instance path
- `_instance_display` => instance display name
- `_is_local_instance` => true if result is from the instance that issued the request
- `_is_local_instance` => true if result is from the instance that issued the request

## Failed installation recovery

Failed installations can be recovered manually, eg. if only `update.php` failed. If you can "fix"
the broken installation manually, you can run 

        php extensions/BlueSpiceWikiFarm/maintenance/SetInstanceReady.php --instance "myinstance"

to mark the instance as ready and make it usable.

If, instance cannot be recovered (eg. installation failed), you can remove it in two ways:

- run `php extensions/BlueSpiceWikiFarm/maintenance/RemoveOrphanedDatabaseInstances.php` script
to remove all broken installations. This will NOT remove the database, only instance vault and
entry in database management table.
- Go to Special:FarmManagement, where instance is listed in grey, and remove it using UI. This will
completely remove the instance, including database.

# Instance templates

As before, wiki can be created by cloning another instance, using that instance as a template. But now, templates
can also be defined statically, as a collection of pages, images and configs to auto-import to the newly created instance.

## Creating a template

Create a template directory

       /path/to/templates/dummy
            --> pages.xml 
            --> images/
                --> image1.png
                --> image2.png
            --> config.json

The `pages.xml` file is a standard MediaWiki XML export file, containing all pages to be imported.
The `images/` directory contains all images to be imported.
The `config.json` file contains a JSON object with key/value pairs of MW config variables to be set

    {
        "wgSitename": "My Dummy Wiki",
        "wgSomeVar": "Some value",
        ....
    }


All of these are optional, and if missing it will be skipped.

## Configure template to be available

Add the following to your `LocalSettings.php`:

    $wgWikiFarmInstanceTemplates['dummy'] = [
        'label' => 'Dummy', // Literal string or a message key
        'description' => 'This is a dummy template', // Literal string or a message key
        'source' => '/path/to/templates/dummy', // Path to the template directory
    ];

## Using the template

On the instance creation page, you can select the template from the list on the right. Everything else is done automatically.

