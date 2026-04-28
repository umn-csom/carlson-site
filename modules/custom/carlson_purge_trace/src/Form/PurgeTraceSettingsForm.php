<?php

namespace Drupal\carlson_purge_trace\Form;

use Drupal\carlson_purge_trace\Service\PurgeTraceRuntime;
use Drupal\carlson_purge_trace\Service\PurgeTraceWriter;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
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
   * @var \Drupal\carlson_purge_trace\Service\PurgeTraceWriter
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
      $container->get('carlson_purge_trace.writer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carlson_purge_trace_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $status = $this->writer->getStatus();
    $trace_files = $this->writer->listFiles();
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
    $form['status']['trace_storage'] = [
      '#type' => 'details',
      '#title' => $this->t('Stored trace folders and files'),
      '#description' => $this->t(
        'Trace records are grouped into UTC date folders. Each folder '
        . 'contains one NDJSON file per UTC hour when traces were written.',
      ),
      '#open' => FALSE,
    ];

    if ($trace_files === []) {
      $form['status']['trace_storage']['empty'] = [
        '#plain_text' => $this->t('No purge trace files found.'),
      ];
    }
    else {
      foreach ($this->groupTraceFilesByDate($trace_files) as $date => $files) {
        $form['status']['trace_storage'][$date] = [
          '#type' => 'details',
          '#title' => $this->t('Folder: @date', ['@date' => $date]),
          '#open' => FALSE,
          'files' => [
            '#type' => 'table',
            '#header' => [
              $this->t('File'),
              $this->t('Size'),
            ],
            '#rows' => array_map(
              fn(array $file): array => [
                $file['filename'],
                ByteSizeMarkup::create((int) $file['size']),
              ],
              $files,
            ),
          ],
        ];
      }
    }

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Log purge invalidations'),
      '#default_value' => $enabled,
      '#description' => $this->t(
        'Master switch for purge tracing. When enabled, any request or '
        . 'Drush command that invalidates cache tags writes one summary '
        . 'record to private storage. Turn this on only while reproducing '
        . 'or measuring a purge problem.',
      ),
    ];
    $form['capture_callers'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include PHP caller stack frames'),
      '#default_value' => $capture_callers,
      '#description' => $this->t(
        'Adds a small stack trace to each invalidation call so you can see '
        . 'which code path triggered the purge. Useful for imports, cron, '
        . 'and indirect entity saves, but it increases log size and should '
        . 'normally stay off until you need source-level debugging.',
      ),
    ];
    $form['estimate_cache_object_impact'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Count matching cache records'),
      '#default_value' => $estimate_cache_object_impact,
      '#description' => $this->t(
        'For broad tags such as node list tags, query selected Drupal cache '
        . 'tables and log how many current cache records match, plus a few '
        . 'sample cache IDs. This helps estimate Drupal-side cache impact, '
        . 'not CDN page count. It adds database work during traced requests, '
        . 'so keep it off unless you are investigating blast radius.',
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
        'Keep trace files for this many days. Older date folders are '
        . 'removed automatically the next time a trace is written.',
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
   * Groups trace file metadata by UTC date folder.
   *
   * @param array<int, array<string, mixed>> $files
   *   Trace file metadata.
   *
   * @return array<string, array<int, array<string, mixed>>>
   *   Files keyed by date folder.
   */
  protected function groupTraceFilesByDate(array $files): array {
    $grouped = [];

    foreach ($files as $file) {
      $date = (string) ($file['date'] ?? $this->t('Unknown date'));
      $grouped[$date][] = $file;
    }

    krsort($grouped);
    return $grouped;
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
