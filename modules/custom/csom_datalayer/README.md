# CSOM DataLayer
Get content/entity/user data from inside Drupal to the client-side/front-end. Outputs various CMS page meta data (like content type, author uid, taxonomy terms), which can be used for all kinds of front-end features. This works for all entity types and is easy to extend with hooks.

## Dependancies:
* [DataLayer](https://www.drupal.org/project/datalayer)
  * Install with Composer (at project module root) : $ composer require 'drupal/datalayer:^1.0'
  * Ability to CURL
  * Access to the Slate Server, and Whitelisting on the Carlson Boomi Web Services server.

Install:
1. First install the Dependancies above and turn it/them on in your Drupal Site under the Extend menu (Dependancies are now included. Should auto install them, if not present.)
1. Next install this code in your /Modules/custom/csom_datalayer directory
1. This will create a table on the database server called *csom_slate_status* (Sales Funnel Status Data) and *csom_piwik_status* (Marketing Web Site Visits Data)
1. Configure the "Configuration" > "System" > "CSOM Datalayer settings form" with Web Service End Point information.

Activate:
1. Turn it on in your Drupal Site under the Extend menu it will be called CSOM DataLayer (csom_datalayer)

Load Data:
1. (Temporary method to be replaced at later time) Update site analytics tables based on querystring parameter
  * add to any drupal web frontend page: *?super_secret_update_param*
  * update Slate Program Status Data only: *?update_slate_status_data*
  * update Motamo/Piwik Visitor Status Data only: *?update_visitor_status_data*

  