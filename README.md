# drupal-8
Carlson School of Management Drupal 8 Migration

# import users
lando drush migrate-import custom_user

# patches

Fixes the issue with running: 'drush migrate-status'.
$ cd docroot
$ curl https://www.drupal.org/files/issues/2018-07-13/2981225-11.patch | patch -p1
