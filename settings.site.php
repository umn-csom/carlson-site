<?php

// phpcs:ignoreFile

/**
 * @file
 * Drupal site settings for UMN-specific multi-site configuration.
 *
 * Since settings.php is ignored by UMN's Acquia Cloud environments, all
 * shared configurations and settings must be placed in this file. This
 * file is picked up across local and remote environments.
 */

// Load local vendor autoloader for site-specific libraries.
$local_autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($local_autoload)) {
  require_once $local_autoload;
}

/*
 * Increase site memory
*/
ini_set('memory_limit', '768M');

/**
 * Sync directory for configurations.
 */
$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/docroot/sites/carlsonschool.umn.edu/config/sync';

/**
 * Config Split default settings.
 */
// We don't use config split on this project.
$config['config_split.config_split.local']['status'] = FALSE;
$config['config_split.config_split.stage']['status'] = FALSE;
$config['config_split.config_split.prod']['status'] = FALSE;
$config['config_split.config_split.dev']['status'] = FALSE;

/**
 * Environment Indicator default settings.
 */
$config['environment_indicator.indicator']['fg_color'] = '#FFFFFF';

/**
 * Environment-specific settings.
 */
$environment = $_ENV['AH_SITE_ENVIRONMENT'] ?? 'local';
switch ($environment) {
  case 'dev':
    $base_url = 'https://carlsonschool.dev.umn.edu';
    $config['environment_indicator.indicator']['bg_color'] = '#4DB9AF';
    $config['environment_indicator.indicator']['name'] = 'DEV';
    $config['system.logging']['error_level'] = 'verbose';
    break;

  case 'test':
    $base_url = 'https://carlsonschool.stg.umn.edu';
    $config['environment_indicator.indicator']['bg_color'] = '#F8AF1F';
    $config['environment_indicator.indicator']['name'] = 'STAGE';
    $config['system.logging']['error_level'] = 'verbose';
    break;

  case 'prod':
    $base_url = 'https://carlsonschool.umn.edu';
    $config['environment_indicator.indicator']['bg_color'] = '#E84E36';
    $config['environment_indicator.indicator']['name'] = 'PROD';
    $config['shield.settings']['shield_enable'] = FALSE;
    break;

  case 'local':
    $base_url = 'https://carlsonschool.ddev.site';
    $config['environment_indicator.indicator']['bg_color'] = '#4D4D4D';
    $config['environment_indicator.indicator']['name'] = 'LOCAL';
    $config['shield.settings']['shield_enable'] = FALSE;
    $config['system.logging']['error_level'] = 'verbose';
}

// Block robots from indexing non-prod environments.
if (
  !isset($_ENV['SERVER_NAME']) ||
  $_ENV['SERVER_NAME'] !== 'carlsonschool.umn.edu'
) {
  $config['metatag.metatag_defaults.global']['tags']['robots'] = 'noindex, nofollow, noimageindex';
}

// Hardcode Stage File Proxy module logic.
if (in_array($environment, ['local', 'dev', 'test'])) {
  $origin = 'https://carlsonschool.umn.edu';
  $origin_dir = 'sites/carlsonschool.umn.edu/files';
  $config['stage_file_proxy.settings']['origin'] = $origin;
  $config['stage_file_proxy.settings']['origin_dir'] = $origin_dir;
  $config['stage_file_proxy.settings']['proxy_headers'] = '';
}

// Override OIT page cache TTL: 2764800 (32 days) -> 300 (5 min).
// See https://github.umn.edu/drupalmodules/d8-configurations/blob/11.x-prod/sites-files/base-settings.php#L79
$config['system.performance']['cache']['page']['max_age'] = 300;

// CSM-226: preload checksum values for the two stable Views metadata tags
// approved in Phase 1. Future additions should be backed by warm cachetags
// lookup evidence, not only by page-level cache-tag header frequency.
$settings['cache_preload_tags'] = [
  'views_data',
  'config:core.extension',
];
