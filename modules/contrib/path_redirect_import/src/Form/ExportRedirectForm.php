<?php

namespace Drupal\path_redirect_import\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\path_redirect_import\MigratePluginTrait;
use Drupal\path_redirect_import\RedirectExport;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to export redirects.
 */
class ExportRedirectForm extends FormBase {
  use MigratePluginTrait;

  /**
   * The redirect export service.
   *
   * @var \Drupal\path_redirect_import\RedirectExport
   */
  protected $redirectExport;

  /**
   * Constructs a MigrateRedirectForm object.
   *
   * @param \Drupal\path_redirect_import\RedirectExport $redirect_export
   *   The redirect export service.
   */
  public function __construct(RedirectExport $redirect_export) {
    $this->redirectExport = $redirect_export;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('path_redirect_import.redirect_export')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'migrate_redirect_export_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['markup'] = [
      '#markup' => 'A CSV will be exported with this structure:',
    ];

    $form['pre'] = [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => 'source,destination,language,status_code
source-path,&lt;front&gt;,und,301
source-path-other?param=value,/my-path,en,302
my-source-path,https://example.com,und',
    ];

    $form['actions'] = [
      '#type' => 'actions',
      '#weight' => 100,
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export data'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // No need to validate the form;.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing to send from form for now.
    $this->batchPreparation();
  }

  /**
   * Batch processing function.
   */
  public function batchPreparation() {
    $operations = $this->redirectExport->getBatchOperations();

    if (count($operations) > 0) {
      $batch = [
        'operations' => $operations,
        'title' => $this->t('Exporting redirect entities to file'),
        'init_message' => $this->t('Process started.'),
        'progress_message' => $this->t('Exporting...'),
        'error_message' => $this->t('An error occurred while exporting redirect entities.'),
        'finished' => RedirectExport::class . '::batchFinishedExport',
      ];

      batch_set($batch);
    }
    else {
      $this->messenger()->addError(t('There are no redirections to export.'));
    }
  }

}
