<?php

namespace Drupal\user_expire\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\RoleInterface;

/**
 * User expire admin settings form.
 */
class UserExpireSettingsForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a \Drupal\user_expire\Form\UserExpireSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manger.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($config_factory);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_expire_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['user_expire.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the rules and the roles.
    $config = $this->config('user_expire.settings');
    $rules = $config->get('user_expire_roles') ?: [];
    $user_roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $roles = [];

    foreach ($user_roles as $rid => $role) {
      $roles[$role->id()] = $role->get('label');
    }

    // Save the current roles for use in submit handler.
    $form['current_roles'] = [
      '#type' => 'value',
      '#value' => $roles,
    ];

    $form['frequency'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frequency time in seconds'),
      '#default_value' => $config->get('frequency') ?: 172800,
      '#description' => $this->t('86400 = 1 day'),
    ];

    $form['offset'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Warning offset time in seconds'),
      '#default_value' => $config->get('offset') ?: 604800,
      '#description' => $this->t('86400 = 1 day'),
    ];

    // Now show boxes for each role.
    $form['user_expire_roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('User inactivity expire by role settings'),
      '#description' => $this->t('Configure expiration of users by roles. Enter 0 to disable for the role. Enter 7776000 for 90 days.'),
    ];

    foreach ($roles as $rid => $role) {
      if ($rid === RoleInterface::ANONYMOUS_ID) {
        continue;
      }

      $form['user_expire_roles']['user_expire_' . $rid] = [
        '#type' => 'textfield',
        '#title' => $this->t('Seconds of inactivity before expiring %role users', ['%role' => $role]),
        '#default_value' => $rules[$rid] ?? 0,
      ];
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!ctype_digit($form_state->getValue('frequency'))) {
      $form_state->setErrorByName('frequency', $this->t('Frequency time must be an integer.'));
    }

    if (!ctype_digit($form_state->getValue('offset'))) {
      $form_state->setErrorByName('offset', $this->t('Warning offset time must be an integer.'));
    }

    foreach ($form_state->getValue('current_roles') as $rid => $role) {
      if ($rid === RoleInterface::ANONYMOUS_ID) {
        continue;
      }

      if (!ctype_digit($form_state->getValue('user_expire_' . $rid))) {
        $form_state->setErrorByName('user_expire_' . $rid, $this->t('Inactivity period must be an integer.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->cleanValues();
    $config = $this->config('user_expire.settings');

    if (!empty($form_state->getValue('frequency'))) {
      $config->set('frequency', (int) $form_state->getValue('frequency'));
    }

    if (!empty($form_state->getValue('offset'))) {
      $config->set('offset', (int) $form_state->getValue('offset'));
    }

    // Insert the rows that were inserted.
    $rules = $config->get('user_expire_roles') ?: [];
    foreach ($form_state->getValue('current_roles') as $rid => $role) {
      // Only save non-zero values.
      if (!empty($form_state->getValue('user_expire_' . $rid))) {
        $rules[$rid] = (int) $form_state->getValue('user_expire_' . $rid);
      }
    }

    $config->set('user_expire_roles', $rules);
    $config->save();
  }

}
