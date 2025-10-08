<?php

namespace Drupal\default_education_abroad_fields\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class DefaultEducationAbroadFieldsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['default_education_abroad_fields.settings'];
  }

  public function getFormId() {
    return 'default_education_abroad_fields_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('default_education_abroad_fields.settings');

    $form['description'] = [
      '#markup' => '<p>Set the WYSIWYG text shown when the specified fields in Education Abroad nodes are empty.</p>',
    ];

    // Fields we want to configure.
    $fields = [
      'field_ea_program_objective' => $this->t('Objective'),
      'field_ea_program_housing_food'  => $this->t('Housing/Food'),
      'field_ea_program_academics' => $this->t('Academics and Credits'),
      'field_visa_and_passport' => $this->t('Visa and Passport'),
      'field_ea_program_app_admission' => $this->t('Application and Admissions'),
      'field_ea_program_aid_scholarhips' => $this->t('Financial Aid and Scholarship'),
    ];

    foreach ($fields as $field_name => $label) {
      $saved_value = $config->get($field_name) ?: ['value' => '', 'format' => 'rich_text'];

      $form[$field_name] = [
        '#type' => 'text_format',
        '#title' => $this->t('@label', ['@label' => $label]),
        '#format' => $saved_value['format'],
        '#default_value' => $saved_value['value'],
        '#description' => $this->t('Text or markup to display when @label is empty.', ['@label' => strtolower($label)]),
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('default_education_abroad_fields.settings');
    foreach (['field_ea_program_objective','field_ea_program_housing_food','field_ea_program_academics','field_visa_and_passport','field_ea_program_app_admission','field_ea_program_aid_scholarhips'] as $field_name) {
      $value = $form_state->getValue($field_name);
      $config->set($field_name, [
        'value' => $value['value'],
        'format' => $value['format'],
      ]);

    }
    $config->save();
    parent::submitForm($form, $form_state);
  }
}
