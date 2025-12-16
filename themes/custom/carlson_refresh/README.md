# Carlson Refresh Theme

Based on the **Bootstrap 4 - Barrio SASS Starter Kit** for Drupal.

* [Project page][1]
* [Documentation][2]
* [Code branch: 8.x-4.12][3]

The theme has been altered from the Bootstrap defaults to uses Node.js
and npm with `node-sass`, `postcss`, and `critical` library dependencies
to compile SCSS and build Critical Path CSS styles.

*All compiled assets are committed to the repository.*

## Installation

We recommend you **install and run the frontend dependencies and tooling inside the ddev environment**, to ensure consistent environments between developers.

See [Installation](../../../README.md#Installation).

1.  Install Node.js 11 with nvm.

        cd path/to/themes/custom/carlson_refresh
        nvm install 11

2.  Install frontend dependencies.

        nvm use 11
        npm install

3.  Compile stylesheets.

        npm run build

4.  Watch for style changes.

        npm run watch

5.  Generate Critical Path CSS.

        npm run critical

6.  Minify Critical Path CSS.

        npm run minify-critical


[1]: https://www.drupal.org/project/bootstrap_sass
[2]: https://www.drupal.org/docs/contributed-themes/bootstrap-45-barrio-sass-starter-kit
[3]: https://git.drupalcode.org/project/bootstrap_sass/-/tree/8.x-4.12?ref_type=tags
