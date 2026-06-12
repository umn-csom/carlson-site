<?php

namespace Drupal\carlson_purge_trace\Controller;

use Drupal\carlson_purge_trace\Service\PurgeTraceRuntime;
use Drupal\carlson_purge_trace\Service\PurgeTraceWriter;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists and serves purge trace files.
 */
class PurgeTraceLogsController extends ControllerBase {

  /**
   * The writer service.
   *
   * @var \Drupal\carlson_purge_trace\Service\PurgeTraceWriter
   */
  protected PurgeTraceWriter $writer;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * Constructs the controller.
   */
  public function __construct(
    PurgeTraceWriter $writer,
    StateInterface $state,
    DateFormatterInterface $date_formatter,
  ) {
    $this->writer = $writer;
    $this->state = $state;
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('carlson_purge_trace.writer'),
      $container->get('state'),
      $container->get('date.formatter'),
    );
  }

  /**
   * Builds the purge trace report page.
   */
  public function content(): array {
    $status = $this->writer->getStatus();
    $files = $this->writer->listFiles();

    $build['summary'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Status'),
      '#items' => [
        $this->t('Capture enabled: @value', [
          '@value' => $this->state->get(
            PurgeTraceRuntime::STATE_ENABLED,
            PurgeTraceRuntime::DEFAULT_ENABLED,
          )
            ? $this->t('Yes')
            : $this->t('No'),
        ]),
        $this->t('Caller stack samples: @value', [
          '@value' => $this->state->get(
            PurgeTraceRuntime::STATE_CAPTURE_CALLERS,
            PurgeTraceRuntime::DEFAULT_CAPTURE_CALLERS,
          )
            ? $this->t('Yes')
            : $this->t('No'),
        ]),
        $this->t('Cache object impact estimation: @value', [
          '@value' => $this->state->get(
            PurgeTraceRuntime::STATE_ESTIMATE_CACHE_OBJECT_IMPACT,
            PurgeTraceRuntime::DEFAULT_ESTIMATE_CACHE_OBJECT_IMPACT,
          )
            ? $this->t('Yes')
            : $this->t('No'),
        ]),
        $this->t('private:// available: @value', [
          '@value' => $status['private_available'] ? $this->t('Yes') : $this->t('No'),
        ]),
        $this->t('Retention days: @value', [
          '@value' => (int) $this->state->get(
            PurgeTraceRuntime::STATE_RETENTION_DAYS,
            PurgeTraceRuntime::DEFAULT_RETENTION_DAYS,
          ),
        ]),
        $this->t('Base URI: @value', ['@value' => $status['base_uri']]),
        $this->t('Resolved path: @value', [
          '@value' => $status['base_path'] ?: $this->t('Unavailable'),
        ]),
        Link::fromTextAndUrl(
          $this->t('Open purge trace settings'),
          Url::fromRoute('carlson_purge_trace.settings'),
        )->toString(),
        Link::fromTextAndUrl(
          $this->t('Run validation probe'),
          Url::fromRoute('carlson_purge_trace.probe'),
        )->toString(),
      ],
    ];

    if ($files !== []) {
      $build['summary']['#items'][] = Link::fromTextAndUrl(
        $this->t('Download all trace files'),
        Url::fromRoute('carlson_purge_trace.download_all'),
      )->toString();
    }

    $build['files'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Date'),
        $this->t('File'),
        $this->t('Size'),
        $this->t('Modified'),
        $this->t('Actions'),
      ],
      '#rows' => [],
      '#empty' => $this->t('No purge trace files found.'),
    ];

    foreach ($files as $file) {
      $view_url = Url::fromRoute('carlson_purge_trace.view', [
        'date' => $file['date'],
        'filename' => $file['filename'],
      ]);
      $download_url = Url::fromRoute('carlson_purge_trace.download', [
        'date' => $file['date'],
        'filename' => $file['filename'],
      ]);

      $build['files']['#rows'][] = [
        $file['date'],
        $file['filename'],
        ByteSizeMarkup::create((int) $file['size']),
        $this->dateFormatter->format($file['modified'], 'short'),
        [
          'data' => [
            '#type' => 'operations',
            '#links' => [
              'view' => [
                'title' => $this->t('View'),
                'url' => $view_url,
              ],
              'download' => [
                'title' => $this->t('Download'),
                'url' => $download_url,
              ],
            ],
          ],
        ],
      ];
    }

    return $build;
  }

  /**
   * Renders a trace file in the browser.
   */
  public function viewFile(string $date, string $filename): array {
    try {
      $contents = $this->writer->readFile($date, $filename);
    }
    catch (\Throwable) {
      throw new NotFoundHttpException();
    }

    $records = $this->decodeTraceRecords($contents);
    $build = [
      '#type' => 'container',
      '#cache' => [
        'max-age' => 0,
      ],
      'summary' => [
        '#theme' => 'item_list',
        '#title' => $this->t('Trace file summary'),
        '#items' => [
          $this->t('File: @file', ['@file' => $filename]),
          $this->t('Date directory: @date', ['@date' => $date]),
          $this->t('Trace records: @count', ['@count' => count($records)]),
        ],
      ],
    ];

    if ($records === []) {
      $build['empty'] = [
        '#markup' => $this->t('No valid purge trace records found.'),
      ];
      return $build;
    }

    foreach ($records as $record) {
      $build['records'][] = $this->buildTraceRecord($record);
    }

    return $build;
  }

  /**
   * Downloads a trace file.
   */
  public function downloadFile(string $date, string $filename): BinaryFileResponse {
    try {
      $path = $this->writer->resolveFilePath($date, $filename);
    }
    catch (\Throwable) {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse($path);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      basename($path),
    );
    return $response;
  }

  /**
   * Downloads all current trace files as one NDJSON file.
   */
  public function downloadAllFiles(): StreamedResponse {
    $files = $this->writer->listFiles();

    usort($files, static function (array $a, array $b): int {
      return ((string) $a['relative_path']) <=> ((string) $b['relative_path']);
    });

    $response = new StreamedResponse(function () use ($files): void {
      foreach ($files as $file) {
        $path = $file['path'] ?? NULL;
        if (!is_string($path) || !is_file($path)) {
          continue;
        }

        $contents = file_get_contents($path);
        if ($contents === FALSE || $contents === '') {
          continue;
        }

        echo rtrim($contents, "\r\n") . PHP_EOL;
      }
    });
    $response->headers->set('Content-Type', 'application/x-ndjson');
    $response->headers->set(
      'Content-Disposition',
      $response->headers->makeDisposition(
        ResponseHeaderBag::DISPOSITION_ATTACHMENT,
        'purge-trace-all-' . gmdate('Y-m-d-His') . '.ndjson',
      ),
    );
    return $response;
  }

  /**
   * Decodes newline-delimited trace records.
   *
   * @return array<int, array<string, mixed>>
   *   Valid trace records.
   */
  protected function decodeTraceRecords(string $contents): array {
    $records = [];
    $lines = preg_split('/\R/', trim($contents));

    foreach ($lines as $index => $line) {
      if ($line === '') {
        continue;
      }

      try {
        $record = Json::decode($line);
      }
      catch (\Throwable) {
        continue;
      }

      if (!is_array($record)) {
        continue;
      }

      $record['_line_number'] = $index + 1;
      $records[] = $record;
    }

    return $records;
  }

  /**
   * Builds a readable render array for one trace record.
   *
   * @param array<string, mixed> $record
   *   A decoded trace record.
   *
   * @return array<string, mixed>
   *   The record render array.
   */
  protected function buildTraceRecord(array $record): array {
    $recorded_at = $this->formatTraceTimestamp($record['recorded_at'] ?? NULL);
    $title = $this->t(
      '@time | @context | @blast blast radius | @calls invalidation calls',
      [
        '@time' => $recorded_at,
        '@context' => $record['execution_context'] ?? $this->t('unknown'),
        '@blast' => $record['blast_radius'] ?? $this->t('unknown'),
        '@calls' => $record['invalidation_call_count'] ?? 0,
      ],
    );

    return [
      '#type' => 'details',
      '#title' => $title,
      '#open' => FALSE,
      'summary' => $this->buildSummaryTable($record),
      'broad_tags' => $this->buildStringList(
        $this->t('Broad tags'),
        $record['broad_tags'] ?? [],
        $this->t('No broad tags recorded.'),
      ),
      'tag_families' => $this->buildTagFamiliesTable(
        $record['tag_families'] ?? [],
      ),
      'causes' => $this->buildCausesTable($record['causes'] ?? []),
      'invalidation_calls' => $this->buildInvalidationCallsTable(
        $record['invalidation_calls'] ?? [],
      ),
      'unique_tags' => $this->buildStringList(
        $this->t('Unique tags'),
        $record['unique_tags'] ?? [],
        $this->t('No unique tags recorded.'),
      ),
      'raw' => $this->buildRawJsonDetails($record),
    ];
  }

  /**
   * Builds the top-level trace summary table.
   *
   * @param array<string, mixed> $record
   *   A decoded trace record.
   *
   * @return array<string, mixed>
   *   The summary table render array.
   */
  protected function buildSummaryTable(array $record): array {
    $request = is_array($record['request'] ?? NULL) ? $record['request'] : [];
    $user = is_array($record['user'] ?? NULL) ? $record['user'] : [];

    return $this->buildKeyValueTable([
      [$this->t('Trace ID'), $record['trace_id'] ?? ''],
      [
        $this->t('Recorded at'),
        $this->formatTraceTimestamp($record['recorded_at'] ?? NULL),
      ],
      [$this->t('Duration'), $this->t('@ms ms', [
        '@ms' => $record['duration_ms'] ?? 0,
      ])],
      [$this->t('Execution context'), $record['execution_context'] ?? ''],
      [$this->t('Command'), $record['command'] ?? ''],
      [$this->t('Route'), $request['route_name'] ?? ''],
      [$this->t('URI'), $request['uri'] ?? ''],
      [$this->t('Method'), $request['method'] ?? ''],
      [$this->t('User ID'), $user['uid'] ?? ''],
      [$this->t('Authenticated'), !empty($user['is_authenticated'])
        ? $this->t('Yes')
        : $this->t('No')],
      [$this->t('Contexts'), implode(', ', $record['contexts'] ?? [])],
      [
        $this->t('Estimated queue items'),
        $record['estimated_queue_items'] ?? 0,
      ],
      [
        $this->t('Estimated Acquia BAN batches'),
        $record['estimated_acquia_ban_batches'] ?? 0,
      ],
      [$this->t('Line number'), $record['_line_number'] ?? ''],
    ]);
  }

  /**
   * Builds a table for simple key-value pairs.
   *
   * @param array<int, array{0: mixed, 1: mixed}> $items
   *   The label-value pairs.
   *
   * @return array<string, mixed>
   *   The table render array.
   */
  protected function buildKeyValueTable(array $items): array {
    $rows = [];
    foreach ($items as [$label, $value]) {
      $rows[] = [
        $label,
        $this->formatTableValue($value),
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Property'),
        $this->t('Value'),
      ],
      '#rows' => $rows,
    ];
  }

  /**
   * Formats a UTC trace timestamp in Drupal's local timezone.
   *
   * @param mixed $timestamp
   *   The trace timestamp value.
   *
   * @return string
   *   The formatted timestamp.
   */
  protected function formatTraceTimestamp(mixed $timestamp): string {
    if (!is_string($timestamp) || $timestamp === '') {
      return (string) $this->t('Unknown time');
    }

    $unix_timestamp = strtotime($timestamp);
    if ($unix_timestamp === FALSE) {
      return $timestamp;
    }

    return $this->dateFormatter->format($unix_timestamp, 'medium');
  }

  /**
   * Builds an item list for string values.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The list title.
   * @param mixed $items
   *   Candidate list items.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $empty
   *   Empty text.
   *
   * @return array<string, mixed>
   *   The item list render array.
   */
  protected function buildStringList($title, mixed $items, $empty): array {
    $items = is_array($items) ? array_values($items) : [];
    $items = array_map('strval', $items);

    return [
      '#theme' => 'item_list',
      '#title' => $title,
      '#items' => $items,
      '#empty' => $empty,
    ];
  }

  /**
   * Builds a tag-family count table.
   *
   * @param mixed $families
   *   Candidate tag-family data.
   *
   * @return array<string, mixed>
   *   The table render array.
   */
  protected function buildTagFamiliesTable(mixed $families): array {
    $rows = [];
    if (is_array($families)) {
      foreach ($families as $family => $count) {
        $rows[] = [
          $this->formatTableValue($family),
          $this->formatTableValue($count),
        ];
      }
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Tag families'),
      '#header' => [
        $this->t('Family'),
        $this->t('Count'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No tag families recorded.'),
    ];
  }

  /**
   * Builds a table of recorded causes.
   *
   * @param mixed $causes
   *   Candidate cause records.
   *
   * @return array<string, mixed>
   *   The table render array.
   */
  protected function buildCausesTable(mixed $causes): array {
    $rows = [];
    if (is_array($causes)) {
      foreach ($causes as $cause) {
        if (!is_array($cause)) {
          continue;
        }

        $rows[] = [
          $this->formatTableValue($cause['type'] ?? ''),
          $this->formatTableValue($cause['operation'] ?? ''),
          $this->formatTableValue(
            $cause['entity_type'] ?? $cause['name'] ?? '',
          ),
          $this->formatTableValue($cause['bundle'] ?? ''),
          $this->formatTableValue($cause['id'] ?? ''),
          $this->formatTableValue($cause['label'] ?? ''),
        ];
      }
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Causes'),
      '#header' => [
        $this->t('Type'),
        $this->t('Operation'),
        $this->t('Target'),
        $this->t('Bundle'),
        $this->t('ID'),
        $this->t('Label'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No entity or config causes recorded.'),
    ];
  }

  /**
   * Builds a table of invalidation calls.
   *
   * @param mixed $calls
   *   Candidate invalidation call records.
   *
   * @return array<string, mixed>
   *   The table render array.
   */
  protected function buildInvalidationCallsTable(mixed $calls): array {
    $rows = [];
    if (is_array($calls)) {
      foreach ($calls as $call) {
        if (!is_array($call)) {
          continue;
        }

        $caller = is_array($call['caller'] ?? NULL) ? $call['caller'] : [];
        $tags = is_array($call['tags'] ?? NULL) ? $call['tags'] : [];
        $rows[] = [
          $this->formatTableValue($call['offset_ms'] ?? 0),
          $this->formatTableValue($call['tag_count'] ?? count($tags)),
          $this->formatTableValue($caller['origin'] ?? ''),
          $this->formatTableValue(implode(', ', $tags)),
        ];
      }
    }

    return [
      '#type' => 'table',
      '#caption' => $this->t('Invalidation calls'),
      '#header' => [
        $this->t('Offset ms'),
        $this->t('Tag count'),
        $this->t('Caller'),
        $this->t('Tags'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No invalidation calls recorded.'),
    ];
  }

  /**
   * Builds the raw JSON fallback details element.
   *
   * @param array<string, mixed> $record
   *   A decoded trace record.
   *
   * @return array<string, mixed>
   *   The details render array.
   */
  protected function buildRawJsonDetails(array $record): array {
    unset($record['_line_number']);

    return [
      '#type' => 'details',
      '#title' => $this->t('Raw JSON'),
      '#open' => FALSE,
      'json' => [
        '#type' => 'inline_template',
        '#template' => '<pre style="white-space: pre-wrap; '
          . 'overflow-wrap: anywhere;">{{ json }}</pre>',
        '#context' => [
          'json' => Json::encode($record),
        ],
      ],
    ];
  }

  /**
   * Formats a value for safe table output.
   *
   * @param mixed $value
   *   The value to format.
   *
   * @return array<string, array<string, string>>
   *   A safe table cell render array.
   */
  protected function formatTableValue(mixed $value): array {
    if (is_bool($value)) {
      $value = $value ? $this->t('Yes') : $this->t('No');
    }
    elseif (is_array($value)) {
      $value = Json::encode($value);
    }
    elseif ($value === NULL) {
      $value = '';
    }

    return [
      'data' => [
        '#plain_text' => (string) $value,
      ],
    ];
  }

}
