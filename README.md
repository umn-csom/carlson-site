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

2.  One time setup of d9 enterprise multisite:

        git clone git@github.umn.edu:drupalplatform/d8-composer.git umn-d9
        cd umn-d9
        git clone git@github.umn.edu:Bluespark/umn-d9-ddev.git .ddev
        ddev auth ssh
        ddev provision-multisite
        ddev composer install
        ddev restart

2.  Install the multisite instance.

    You can install from the Bluespark fork:

        ddev multisite carlsonschool.umn.edu git@github.umn.edu:Bluespark/carlsonschool.umn.edu.git

    Or if you have permission, from the upstream:

        ddev multisite carlsonschool.umn.edu git@github.umn.edu:CarlsonSchool-Web/drupal-8

3.  Install the site-specific dependencies.

        ddev auth ssh
        ddev start
        composer install

4.  Adjust the configuration sync folder in settings.

    Config sync is located in a non-standard folder. Add the following
    lines to docroot/sites/carlsonschool.umn.edu/settings.php

    ```php
    /**
    * Location of the site configuration files.
    */
    $settings['config_sync_directory'] = $app_root . '/' . $site_path . '/config_sync';
    ```

    Then restart Ddev.

        ddev restart

5.  Install the database.

    During the restart you should see a message like this:

    > CREATE DATABASE IF NOT EXISTS carlsonschool; GRANT ALL ON carlsonschool.* to 'db'@'%';

    If you don't see that, it might be due to mutagen not having finished
    syncing the file changes in .ddev/config.multisite.yaml yet. In which case,
    the solution is to re-run ddev restart again.

    Then download the prod database snapshot from the
    [UMN Drupal Management console website][3] (requires UMN Login).

    And import the database:

        ddev import-db --target-db carlsonschool --src ~/Downloads/prod-carlsonschool-*.sql.gz

6.  Enable stage file proxy:

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
    ```

7.  Login as admin:

        ddev drush @carlsonschool.ddev uli

8.  Optionally, re-index solr:

    Note: this site may not leverage UMN / Acquia Solr, but the standard
    documentation exists for future reference.

        ddev drush @carlsonschool.ddev sapi-sc acquia_search_server
        ddev drush @carlsonschool.ddev sapi-i

    Ddev solr core overrides are pre-configured during the `ddev multisite`
    installation step with sensible default values located in
    docroot/sites/carlsonschool.umn.edu/settings.local.php

9.  Do not use the `ddev drush use` subcommand.

    The `drush use` subcommand typically does not work in ddev.
## Frontend Developers

Ignore the `barrio_carlson` theme, it is deprecated.

See the [Carlson Refresh README.md](themes/custom/carlson_refresh/README.md).

[1]: https://carlsonschool.umn.edu
[2]: https://twin-cities.umn.edu
[3]: https://drupalmanagement.umn.edu/carlsonschool.umn.edu
