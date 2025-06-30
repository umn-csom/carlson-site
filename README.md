# Carlson School of Management

Custom code for the main public-facing [Carlson School of Management][1]
website of the [University of Minnesota — Twin Cities][2].

This repository represents the `docroot/sites/carlsonschool.umn.edu` folder
within the Lightning UMN Drupal 9 enterprise installation. See the following
help pages for additional information:

https://it.umn.edu/services-technologies/self-help-guides/github-using-github-enterprise-drupal

https://it.umn.edu/services-technologies/how-tos/drupal-9-set-local-environment

## Environments

|  **Env**  |  **Branch**  |  **URL**   |
|-----------|--------------|------------|
|  Local    |  dev         |  https://carlsonschool.ddev.site/ |
|  Dev      |  dev         |  https://carlsonschool.dev.umn.edu/ |
|  Test     |  test        |  https://carlsonschool.stg.umn.edu/ |
|  Prod     |  main        |  https://carlsonschool.umn.edu/ |

## Pre-Installation Requirement
* A functioning development environment on your local machine with Docker, Docker Desktop, and latest version of Ddev.
* A configured SSH key.
* PHP 8.0
* Composer
* Drush

## Installation

1.  Add your public key to UMN's Enterprise Github.

    https://github.umn.edu/settings/keys

2.  Clone the UMN composer project folder and ddev folder.

    This is a one-time setup step of the Drupal 9 enterprise codebase,
    dependencies, and a companion Ddev multisite configuration.

        git clone git@github.umn.edu:drupalplatform/d8-composer.git umn-d9
        cd umn-d9
        git clone git@github.umn.edu:Bluespark/umn-d9-ddev.git .ddev

3.  Install dependencies and provision ddev.

    These steps should be run on initial installation as well as each time
    you pull updates from the two repositories in the previous step.

        ddev auth ssh
        ddev provision-multisite
        ddev composer install
        ddev restart

    The `ddev provision-multisite` is a custom ddev command to prepare ddev
    for a multisite setup. Scaffolding files are stored in the
    `.ddev/multisite_scaffold/` folder and copied into place in the file system.

4.  Install the multisite instance.

    You can install from the Bluespark fork:

        ddev multisite carlsonschool.umn.edu git@github.umn.edu:Bluespark/carlsonschool.umn.edu.git

    Or if you have permission, from the upstream:

        ddev multisite carlsonschool.umn.edu git@github.umn.edu:CarlsonSchool-Web/drupal-8

    The `ddev multisite` custom command handles several tasks, including:

    * Create a directory for the new site in `docroot/sites/carlsonschool.umn.edu`, linked to the specified git repository.
    * Create the `settings.php` file in the new multisite directory and update the database name.
    * Create the `settings.local.php` file in the new multisite directory and update the stage file proxy settings.
    * Create a Drush alias `@carlsonschool.ddev` with proper the `root` and `uri` option in the `drush/sites/carlsonschool.yml` file.
    * Provision Ddev with a `carlsonschool` database and `carlsonschool.ddev.site` hostname in `.ddev/config.multisite.yaml`.
    * Provision Drupal multisite directory alias for `carlsonschool.ddev.site` in `docroot/sites/sites.php`.

5.  Restart ddev to pick up new database and hostname configurations.

        ddev restart

    During the restart you should see a message like this:

    > CREATE DATABASE IF NOT EXISTS carlsonschool; GRANT ALL ON carlsonschool.* to 'db'@'%';

    If you don't see that, you may need to run `ddev restart` again. This issue
    is typically caused by mutagen not having finished syncing the automated
    file changes that were made to `.ddev/config.multisite.yaml` during the
    `ddev multisite` command in the previous step.

    Then download the prod database snapshot from the
    [UMN Drupal Management console website][3] (requires UMN Login).

    And import the database:

        ddev import-db --target-db carlsonschool --src ~/Downloads/prod-carlsonschool-*.sql.gz

6.  Adjust the configuration sync folder in settings.

    Config sync is located in a non-standard folder for the Carlson website.
    Add the following lines to docroot/sites/carlsonschool.umn.edu/settings.php

    ```php
    /**
    * Location of the site configuration files.
    */
    $settings['config_sync_directory'] = $app_root . '/' . $site_path . '/config_sync';
    ```

    Then restart Ddev.

        ddev restart

7.  Enable stage file proxy:

        ddev drush @carlsonschool.ddev en stage_file_proxy

    The proxy origin settings are pre-configured during the `ddev multisite` installation step with sensible default values located in
    docroot/sites/carlsonschool.umn.edu/settings.local.php

    This file is ignored by git and the values may be changed to pull from a
    different environment, eg *dev*. But please note the origin_dir should
    contain the *prod* domain value.

    ```php
    // Configure stage file proxy origin.
    $config['stage_file_proxy.settings']['origin'] = 'https://carlsonschool.dev.umn.edu';
    $config['stage_file_proxy.settings']['origin_dir'] = 'sites/carlsonschool.umn.edu/files';
    $config['stage_file_proxy.settings']['proxy_headers'] = '';
    ```

8.  Login as admin:

        ddev drush @carlsonschool.ddev uli

9.  Optionally, re-index solr:

    Note: this site may not leverage UMN / Acquia Solr, but the standard
    documentation exists for future reference.

        ddev drush @carlsonschool.ddev sapi-sc acquia_search_server
        ddev drush @carlsonschool.ddev sapi-i

    Ddev solr core overrides are pre-configured during the `ddev multisite`
    installation step with sensible default values located in
    docroot/sites/carlsonschool.umn.edu/settings.local.php

10. Leverage `drush use` appropriately.

    The `drush use` subcommand is useful for multisite installations so you
    do not have to specify the drush alias with each command. However, it
    only works when executed from within the ddev web container via an SSH
    session.

    This works:

        ddev ssh
        drush use @carlsonschool.ddev
        drush st

    This DOES NOT WORK:

        ddev drush use @carlsonschool.ddev
        ddev drush st
## Frontend Developers

Ignore the `barrio_carlson` theme, it is deprecated.

See the [Carlson Refresh README.md](themes/custom/carlson_refresh/README.md).

[1]: https://carlsonschool.umn.edu
[2]: https://twin-cities.umn.edu
[3]: https://drupalmanagement.umn.edu/carlsonschool.umn.edu
