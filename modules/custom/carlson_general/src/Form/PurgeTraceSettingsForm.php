<?php

namespace Drupal\carlson_general\Form;

use Drupal\carlson_general\Service\PurgeTraceRuntime;
use Drupal\carlson_general\Service\PurgeTraceWriter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Temporary settings form for purge trace capture.
 */
class PurgeTraceSettingsForm extends FormBase {

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The writer service.
   *
   * @var \Drupal\carlson_general\Service\PurgeTraceWriter
   */
  protected PurgeTraceWriter $writer;

  /**
   * Constructs the form.
   */
  public function __construct(StateInterface $state, PurgeTraceWriter $writer) {
    $this->state = $state;
    $this->writer = $writer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('state'),
      $container->get('carlson_general.purge_trace.writer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carlson_general_purge_trace_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $status = $this->writer->getStatus();
    $enabled = (bool) $this->state->get(
      PurgeTraceRuntime::STATE_ENABLED,
      PurgeTraceRuntime::DEFAULT_ENABLED,
    );
    $retention_days = (int) $this->state->get(
      PurgeTraceRuntime::STATE_RETENTION_DAYS,
      PurgeTraceRuntime::DEFAULT_RETENTION_DAYS,
    );
    $capture_callers = (bool) $this->state->get(
      PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
      PurgeTraceRuntime::DEFAULT_CAPTURE_CALLERS,
    );
    $estimate_cache_object_impact = (bool) $this->state->get(
      PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
      PurgeTraceRuntime::DEFAULT_ESTIMATE_CACHE_OBJECT_IMPACT,
    );

    $form['status'] = [
      '#type' => 'details',
      '#title' => $this->t('Runtime status'),
      '#open' => TRUE,
    ];
    $form['status']['private_available'] = [
      '#type' => 'item',
      '#title' => $this->t('private:// available'),
      '#markup' => $status['private_available'] ? $this->t('Yes') : $this->t('No'),
    ];
    $form['status']['base_uri'] = [
      '#type' => 'item',
      '#title' => $this->t('Base URI'),
      '#markup' => $status['base_uri'],
    ];
    $form['status']['base_path'] = [
      '#type' => 'item',
      '#title' => $this->t('Resolved path'),
      '#markup' => $status['base_path'] ?: $this->t('Unavailable'),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable purge trace capture'),
      '#default_value' => $enabled,
      '#description' => $this->t(
        'Capture every request or command that invalidates cache tags and '
        . 'write a summarized JSON line to private storage.',
      ),
    ];
    $form['capture_callers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Capture caller stack samples'),
      '#default_value' => $capture_callers,
      '#description' => $this->t(
        'Adds the top caller frames for each invalidation call so imports '
        . 'or indirect save paths are easier to identify.',
      ),
    ];
    $form['estimate_cache_object_impact'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Estimate cache object impact'),
      '#default_value' => $estimate_cache_object_impact,
      '#description' => $this->t(
        'Queries current Drupal cache bins for broad tags and logs matching '
        . 'cache-object counts and sample cache IDs. This adds extra '
        . 'database work and should normally stay off unless you '
        . 'specifically need that signal.',
      ),
    ];
    $form['retention_days'] = [
      '#type' => 'number',
      '#title' => $this->t('Retention in days'),
      '#default_value' => $retention_days,
      '#min' => 1,
      '#max' => 7,
      '#required' => TRUE,
      '#description' => $this->t(
        'Trace directories older than this window are removed '
        . 'automatically when new traces are written.',
      ),
    ];
    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->state->set(
      PurgeTraceRuntime::STATE_ENABLED,
      (bool) $form_state->getValue('enabled'),
    );
    $this->state->set(
      PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
      (bool) $form_state->getValue('capture_callers'),
    );
    $this->state->set(
      PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
      (bool) $form_state->getValue('estimate_cache_object_impact'),
    );
    $this->state->set(
      PurgeTraceRuntime::STATE_RETENTION_DAYS,
      (int) $form_state->getValue('retention_days'),
    );

    $this->messenger()->addStatus($this->t('Purge trace settings saved.'));
  }

}
