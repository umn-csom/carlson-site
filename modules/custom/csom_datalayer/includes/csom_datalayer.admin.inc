<?php


function csom_datalayer_admin_form() {

  $form = array();

  //$default = variable_get('csom_datalayer_data_type');

  $form['csom_datalayer_updateURL_fieldset'] = array(
    '#type' => 'fieldset',
    '#title' => t('Update URL'),
    '#description' => t('Internal URL alias called to run updates on Analytics data tables. 
        Be sure to create some content type and set the URL alias there to match the inputted string here.'),
  );

  $form['csom_datalayer_updateURL_fieldset']['csom_datalayer_updateURL'] = array(
    '#type' => 'textfield',

    '#title' => t('URL to be called'),

    '#default_value' => check_variable(variable_get('csom_datalayer_updateURL')),

    '#size' => 60,

    '#required' => TRUE,
  );


  //$form['#validate'][] = 'csom_datalayer_admin_settings_validate';

  $form['#submit'] = array(
    'csom_datalayer_admin_settings_submit',
  );


  return system_settings_form($form);
}


function csom_datalayer_admin_settings_submit($form, &$form_state) {

  variable_set('csom_datalayer_updateURL', $form_state['values']['csom_datalayer_updateURL']);

}

//Cehck if a update URL exists
function check_variable($var) {
  if ($var == NULL) {
    return "";
  } else {
    return $var;
  }
}

