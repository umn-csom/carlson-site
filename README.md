# Carlson Drupal 9

Run all of the commands below in the terminal.

This file is in reference to the original documentation here:
https://it.umn.edu/drupal-enterprise-8-set-drupal-8-local

## Getting started

#### Start in your local websites directory

```
git clone git@github.umn.edu:drupalplatform/d8-composer.git cts-folwell-drupal
cd cts-folwell-drupal
```

#### Then follow the prompts, they should look like this

```
ddev config
Project name (cts-folwell-drupal): <hit enter>
Docroot Location (current directory): docroot
Create docroot at /Applications/MAMP/htdocs/cts-folwell-drupal/docroot? [Y/n] (yes): yes
Project Type [typo3, backdrop, php, drupal6, drupal7, drupal8, wordpress] (php): drupal9
```

#### Then start ddev

```
ddev start
ddev auth ssh
ddev composer install
```

#### Backup the settings files

```
cp docroot/sites/default/settings.ddev.php tmp.settings.ddev.php
cp docroot/sites/default/settings.php tmp.settings.php
```

#### Remove the old default folder and then clone in this code base
```
rm -rf docroot/sites/default
git clone git@github.umn.edu:CarlsonSchool-Web/drupal-8.git docroot/sites/default
```

#### Restore the proper settings files
```
mv tmp.settings.ddev.php docroot/sites/default/settings.ddev.php
mv tmp.settings.php docroot/sites/default/settings.php
```

#### Reset the ddev config
```
ddev config --project-type=drupal9 --http-port=8099 --https-port=8499
ddev restart
```

#### Import the latest CTS database (mysql)
```
ddev exec drush sql-drop
```

- At the prompt:
```
Do you really want to drop all tables in the database db? (y/n): y
```

- Then import and rebuild your site cache:
```
ddev import-db --src=/path/to/your/database
ddev exec drush cr
```

#### Uninstall modules that may cause conflicts in local development
```
ddev exec drush pm-uninstall -y simplesamlphp_auth memcache acquia_purge purge
```

- At the prompt:
```
Do you really want to continue? (y/n): y
```

#### Configure the Stage File Proxy module
```
ddev exec drush pm-enable -y stage_file_proxy
ddev exec drush cset -y stage_file_proxy.settings origin https://carlsonschool.umn.edu
ddev exec drush cset -y stage_file_proxy.settings origin_dir sites/carlsonschool.umn.edu/files
```

#### Change the temporary directory
```
ddev exec drush config-set system.file path.temporary /tmp
```

- At the prompt:
```
Do you want to update path.temporary key in system.file config? (y/n): y
```

#### Log in as an admin and reset your password

This will provide you with a link to a page to change the username/password for the root drupal admin user.
```
ddev exec drush uli
```
Example change: username: u: oitadmin, p: drupal9

#### Setting up twig debugging and cache disabling

Update the file: "docroot/sites/development.services.yml"
```
parameters:
  http.response.debug_cacheability_headers: true
  twig.config:
    debug: true
    auto_reload: true
    cache: false
services:
  cache.backend.null:
    class: Drupal\Core\Cache\NullBackendFactory
```

And, this file here: "docroot/sites/default/settings.ddev.php" at line 36 add in the lines below:
```
$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/development.services.yml';
$settings['cache']['bins']['render'] = 'cache.backend.null';
$settings['cache']['bins']['dynamic_page_cache'] = 'cache.backend.null';
```

#### Theme control
If you navigate to:
```
/docroot/sites/default/themes/custom/barrio_carlson
```

And, then run:
```
npm install
gulp
```

You can then make any SCSS changes needed.