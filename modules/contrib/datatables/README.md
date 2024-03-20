# DataTables

The DataTables module integrates the DataTables jQuery plugin into Drupal which
provides advanced interaction controls to HTML tables such as dynamic
pagination, on-the-fly filtering, and column sorting.

- For a full description of the module, visit the project page:
   <https://www.drupal.org/project/datatables>
- To submit bug reports and feature suggestions, or to track changes:
   <https://www.drupal.org/project/issues/search/datatables>
- For full documentation and examples, visit the DataTables plugin page:
   <http://datatables.net>


## Requirements

This module requires the following library (see Installation):
- [DataTables library](https://datatables.net/download/index)


## Installation

- Module:
    Install as you would normally install a contributed Drupal module.
    See: [Installing Modules](https://www.drupal.org/docs/extending-drupal/installing-modules)
    for further information.
    Install with composer via `composer require 'drupal/datatables:^2.0'`,
    then enable the module as usal.
- Plugin:
    Download the latest DataTables jQuery plugin version 1.10.
    See: [DataTables library](https://datatables.net/download/index)
    Download with Composer via `composer require 'datatables/datatables:^1.10'`
    and move the contents of the vendor/datatables directory into the
    libraries/datatables directory.
    Copy to libraries from vendor with Composer:
    Edit the `composer.json` file of your website and under the "extra" entry and
    the "installer-paths" subentry and juste after line
            "web/libraries/{$name}": [
    add,
                "datatables/datatables",
    And install `mnsami/composer-custom-directory-installer` via
    `composer require 'mnsami/composer-custom-directory-installer:^2.0'`
    From now, `composer update` command will update datatables libraries.


## Configuration

- Create a new view at Structure » Views » Add new view
- Select DataTables as the view style.
- Add fields to show in the table.
