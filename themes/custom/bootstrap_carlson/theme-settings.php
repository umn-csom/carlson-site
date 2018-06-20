<?php

/**
 * @file
 * Contains the theme's settings form.
 */

use \Drupal\Core\Form\FormStateInterface;

/**
 * Implements hook_form_system_theme_settings_alter().
 */
function bootstrap_carlson_form_system_theme_settings_alter(&$form, FormStateInterface &$form_state, $form_id = NULL) {
  // Work-around for a core bug affecting admin themes. See issue #943212.
  if (isset($form_id)) {
    return;
  }

  unset($form['logo']);
  $form['theme_settings']['#open'] = FALSE;
  $form['favicon']['default_favicon']['#default_value'] = theme_get_setting('favicon.use_default');
  $form['header_options'] = array(
    '#type' => 'details',
    '#title' => t('Header options'),
    '#open' => TRUE,
  );
  $form['header_options']['campus_select'] = array(
    '#type' => 'radios',
    '#title' => t('Select campus'),
    '#default_value' => theme_get_setting('campus_select'),
    '#options' => array(
      'umnhf-campus-tc' => 'Twin Cities',
      'umnhf-campus-c' => 'Crookston',
      'umnhf-campus-d' => 'Duluth',
      'umnhf-campus-e' => 'Extension',
      'umnhf-campus-m' => 'Morris',
      'umnhf-campus-r' => 'Rochester',
    ),
  );
  $form['header_options']['dtd_select'] = array(
    '#type' => 'radios',
    '#title' => t('Use "Driven to Discover"'),
    '#default_value' => theme_get_setting('dtd_select'),
    '#options' => array(
      'dtd' => t('yes'),
      'no-dtd' => t('no'),
    ),
  );
  $form['header_options']['header_color'] = array(
    '#type' => 'radios',
    '#title' => t('Header background color'),
    '#default_value' => theme_get_setting('header_color'),
    '#options' => array(
      'umnhf-background-maroon' => 'maroon',
      'umnhf-background-gold' => 'gold',
    ),
  );
  $form['header_options']['campus_links'] = array(
    '#type' => 'radios',
    '#title' => t('Use campus links'),
    '#options' => array(
      'no-links' => 'No campus links',
      'links' => 'Include campus links',
    ),
    '#description' => t('Choose whether to include links to system campuses.'),
    '#default_value' => theme_get_setting('campus_links'),
  );
  $form['header_options']['myu_text'] = array(
    '#type' => 'radios',
    '#title' => t('MyU link text'),
    '#description' => t('Header MyU link can be configured to either "MyU: For Students, Faculty, and Staff" or just "MyU"'),
    '#options' => array(
      'sfs' => 'use "For Students, Faculty, and Staff',
      'myu' => 'only use "MyU"',
    ),
    '#default_value' => theme_get_setting('myu_text'),
  );
  $form['header_options']['mobile_search'] = array(
    '#type' => 'radios',
    '#options' => array(
      'dropdown' => 'Dropdown',
      'two-tier' => 'Search box always visible',
    ),
    '#default_value' => theme_get_setting('mobile_search'),
    '#title' => t('Mobile search box setting'),
    '#description' => t('Configure how search appears on small screens - expandable with a dropdown button, or always visible'),
  );

  $form['footer_options'] = array(
    '#type' => 'details',
    '#title' => t('Footer options'),
    '#open' => TRUE,
  );
  $form['footer_options']['privacy_link'] = array(
    '#type' => 'textfield',
    '#default_value' => theme_get_setting('privacy_link'),
    '#title' => 'Privacy link',
    '#description' => t('If you are using an alternative privacy statement, replace http://privacy.umn.edu with the appropriate url (include "http://" if on another site, or an absolute path - begins with "/" - if using a page on this website).'),
  );
  $form['footer_options']['footer_date'] = array(
    '#type' => 'radios',
    '#title' => t('Use "Current as of X" Date?'),
    '#options' => array(
      0 => 'No',
      1 => 'Yes',
    ),
    '#default_value' => theme_get_setting('footer_date'),
  );

  // Create the form using Forms API: http://api.drupal.org/api/7
  /* -- Delete this line if you want to use this setting
  $form['bootstrap_umn_example'] = array(
  '#type'          => 'checkbox',
  '#title'         => t('bootstrap_umn sample setting'),
  '#default_value' => theme_get_setting('bootstrap_umn_example'),
  '#description'   => t("This example option doesn't do anything."),
  );
  // */

  /* -- Delete this line if you want to remove this base theme setting.
  // We don't need breadcrumbs to be configurable on this site.
  unset($form['breadcrumb']);
  // */

  // We are editing the $form in place, so we don't need to return anything.
}
