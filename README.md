Carlson School of Management Drupal 8 Migration

# Migrations

For migrations, you'll need to add this to your 'settings.local.php' file.
This is assuming your D7 database is in the same location as your D8 one.

```
$databases['migrate']['default'] = array (
  'database' => 'drupal7',
  'username' => 'drupal8',
  'password' => 'drupal8',
  'prefix' => '',
  'host' => 'database',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
);
```

## Frontend Style Guide Build
```
gulp
```