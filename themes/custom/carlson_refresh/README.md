# Carlson Refresh Theme

Based on the **Bootstrap 4 - Barrio SASS Starter Kit** for Drupal.

It uses Node.js, NMP (and nvm) for frontend dependencies and Gulp
as a task runner to compile CSS and Javascript.

*All compiled assets are committed to the repository.*

* [Project page][1]
* [Documentation][2]
* [Code branch: 8.x-4.12][3]

## Installation

1.  Setup the Ddev multisite instance.

    We recommend you **install and run the frontend dependencies and tooling inside the ddev environment**, to ensure consistent environments between developers.

    See [Installation](../../../README.md#Installation).

2.  Install frontend dependencies.

        ddev ssh
        cd docroot/sites/carlsonschool.umn.edu/themes/custom/carlson_refresh
        nvm use 11
        npm install

3.  Compile SASS and watch for frontend changes.

        ddev ssh
        cd docroot/sites/carlsonschool.umn.edu/themes/custom/carlson_refresh
        gulp


[1]: https://www.drupal.org/project/bootstrap_sass
[2]: https://www.drupal.org/docs/contributed-themes/bootstrap-45-barrio-sass-starter-kit
[3]: https://git.drupalcode.org/project/bootstrap_sass/-/tree/8.x-4.12?ref_type=tags
