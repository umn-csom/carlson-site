INTRODUCTION
------------
Integrates the Blurhash library with Drupal.
https://blurha.sh/

REQUIREMENTS
------------
 * Blurhash library - https://registry.npmjs.org/blurhash/-/blurhash-2.0.5.tgz

INSTALLATION
------------

**MANUAL**

Download and extract the Blurhash plugin, rename extracted folder to "blurhash"
and copy it into "web/libraries". The plugin should now be located at
"web/libraries/blurhash/dist/index.js".

**INSTALLATION VIA COMPOSER**

It is assumed you are installing Drupal through Composer using the Drupal
Composer facade. See https://www.drupal.org/docs/develop/using-composer/using-composer-to-manage-drupal-site-dependencies#drupal-packagist

The Blurhash Drupal module is shipped with a "composer.libraries.json" file 
contains information about the library, required by the module itself.

This file should be merged with the project's main composer.json by the aid of
the Composer Merge Plugin plugin available on GitHub. To make use of from the
project directory, open a terminal and run:

```
composer require wikimedia/composer-merge-plugin
```

Then, edit the "composer.json" file of your website and under the "extra"
section add:

```
"merge-plugin": {
    "include": [
        "web/modules/contrib/blurhash/composer.libraries.json"
    ]
}
```

(*) note: the `web` represents the folder where drupal lives like: ex.
`docroot`.

From now on, every time the "composer.json" file is updated, it will also
read the content of "composer.libraries.json" file located at
web/modules/contrib/blurhash/ and update accordingly.

Remember, you may have other entries in there already. For this to work, you
need to have the 'oomphinc/composer-installers-extender' installer. If you
don't have it, or are not sure, simply run:

```
composer require oomphinc/composer-installers-extender
```

Then, run the following composer command:

```
composer require drupal/blurhash
```

This command will add the Blurhash Drupal module and the Blurhash JavaScript library to your
project.


CREDITS
-------
Drupal 8/9/10 - Development, Maintenance
* Stefan Auditor - [sanduhrs](https://www.drupal.org/u/sanduhrs)
