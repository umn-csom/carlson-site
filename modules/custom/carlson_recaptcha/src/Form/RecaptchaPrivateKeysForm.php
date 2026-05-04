<?php

namespace Drupal\carlson_recaptcha\Form;

use Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an admin form for writing private reCAPTCHA key files.
 */
class RecaptchaPrivateKeysForm extends FormBase {

  /**
   * The reCAPTCHA key fields written to JSON.
   */
  protected const KEY_FIELDS = [
    'site_key' => 'site key',
    'secret_key' => 'secret key',
  ];

  /**
   * The private key file service.
   *
   * @var \Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile
   */
  protected RecaptchaPrivateKeyFile $keyFile;

  /**
   * Constructs the form.
   *
   * @param \Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile $key_file
   *   The private key file service.
   */
  public function __construct(RecaptchaPrivateKeyFile $key_file) {
    $this->keyFile = $key_file;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('carlson_recaptcha.recaptcha_private_key_file'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carlson_recaptcha_private_keys';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $existing_keys = $this->keyFile->read();
    $target_path = $this->keyFile->getTargetPath();

    $form['target'] = [
      '#type' => 'details',
      '#title' => $this->t('Target private file'),
      '#open' => TRUE,
    ];
    $form['target']['environment'] = [
      '#type' => 'item',
      '#title' => $this->t('Environment'),
      '#plain_text' => $this->keyFile->getCurrentEnvironment(),
    ];
    $form['target']['file_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('File URI'),
      '#plain_text' => $this->keyFile->getTargetUri(),
    ];
    $form['target']['resolved_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Resolved path'),
      '#plain_text' => $target_path ?: (string) $this->t('Unavailable'),
    ];
    $form['target']['status'] = [
      '#type' => 'item',
      '#title' => $this->t('Current file'),
      '#plain_text' => $existing_keys
        ? (string) $this->t('Configured')
        : (string) $this->t('Missing or empty'),
    ];

    foreach ($this->getKeyVersions() as $version => $label) {
      $form[$version] = [
        '#type' => 'details',
        '#title' => $this->t('@label keys', ['@label' => $label]),
        '#open' => TRUE,
      ];

      foreach (self::KEY_FIELDS as $key => $key_label) {
        $field_name = $this->getFieldName($version, $key);
        $has_existing_value = $this->keyFile->hasValue(
          $existing_keys,
          $version,
          $key,
        );

        $form[$version][$field_name] = [
          '#type' => $key === 'secret_key' ? 'password' : 'textfield',
          '#title' => $this->t('@label @key', [
            '@label' => $label,
            '@key' => $key_label,
          ]),
          '#description' => $has_existing_value
            ? $this->t('Leave blank to keep the existing value.')
            : $this->t('Required because no existing value is configured.'),
          '#attributes' => [
            'autocomplete' => 'new-password',
            'placeholder' => $has_existing_value
              ? (string) $this->t('Configured')
              : (string) $this->t('Not configured'),
          ],
        ];
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save private key file'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    if (!$this->keyFile->isPrivateAvailable()) {
      $form_state->setErrorByName(
        'target',
        $this->t('The private file system is not available.'),
      );
      return;
    }

    $existing_keys = $this->keyFile->read();
    foreach ($this->getKeyVersions() as $version => $label) {
      foreach (self::KEY_FIELDS as $key => $key_label) {
        $field_name = $this->getFieldName($version, $key);
        $submitted_value = trim((string) $form_state->getValue($field_name));

        if (
          $submitted_value === '' &&
          !$this->keyFile->hasValue($existing_keys, $version, $key)
        ) {
          $form_state->setErrorByName(
            $field_name,
            $this->t('@label @key is required.', [
              '@label' => $label,
              '@key' => $key_label,
            ]),
          );
          continue;
        }

        if ($submitted_value !== '' && preg_match('/\s/', $submitted_value)) {
          $form_state->setErrorByName(
            $field_name,
            $this->t('@label @key cannot contain whitespace.', [
              '@label' => $label,
              '@key' => $key_label,
            ]),
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $keys = $this->keyFile->read();

    foreach (array_keys($this->getKeyVersions()) as $version) {
      foreach (array_keys(self::KEY_FIELDS) as $key) {
        $submitted_value = trim((string) $form_state->getValue(
          $this->getFieldName($version, $key),
        ));

        if ($submitted_value !== '') {
          $keys[$version][$key] = $submitted_value;
        }
      }
    }

    if (!$this->keyFile->write($keys)) {
      $this->messenger()->addError($this->t(
        'The private reCAPTCHA key file could not be written.',
      ));
      return;
    }

    $this->messenger()->addStatus($this->t(
      'The private reCAPTCHA key file has been saved. Clear caches before '
      . 'testing forms.',
    ));
  }

  /**
   * Returns the supported key versions.
   *
   * @return array<string, string>
   *   Labels keyed by JSON version key.
   */
  protected function getKeyVersions(): array {
    return [
      'v2' => 'reCAPTCHA v2 fallback',
      'v3' => 'reCAPTCHA v3',
    ];
  }

  /**
   * Gets a form field name for a key value.
   *
   * @param string $version
   *   The key version.
   * @param string $key
   *   The key name.
   *
   * @return string
   *   The form field name.
   */
  protected function getFieldName(string $version, string $key): string {
    return $version . '_' . $key;
  }

}
