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
