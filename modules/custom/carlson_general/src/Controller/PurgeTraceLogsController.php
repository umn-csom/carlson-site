<?php

namespace Drupal\carlson_general\Controller;

use Drupal\carlson_general\Service\PurgeTraceRuntime;
use Drupal\carlson_general\Service\PurgeTraceWriter;
use Drupal\Component\Utility\Html;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\Url;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Lists and serves purge trace files.
 */
class PurgeTraceLogsController extends ControllerBase {

  /**
   * The writer service.
   *
   * @var \Drupal\carlson_general\Service\PurgeTraceWriter
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
      $container->get('carlson_general.purge_trace.writer'),
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
          '@value' => $this->state->get(PurgeTraceRuntime::STATE_ENABLED, FALSE)
            ? $this->t('Yes')
            : $this->t('No'),
        ]),
        $this->t('private:// available: @value', [
          '@value' => $status['private_available'] ? $this->t('Yes') : $this->t('No'),
        ]),
        $this->t('Retention days: @value', [
          '@value' => (int) $this->state->get(
            PurgeTraceRuntime::STATE_RETENTION_DAYS,
            3,
          ),
        ]),
        $this->t('Base URI: @value', ['@value' => $status['base_uri']]),
        $this->t('Resolved path: @value', [
          '@value' => $status['base_path'] ?: $this->t('Unavailable'),
        ]),
        Link::fromTextAndUrl(
          $this->t('Open purge trace settings'),
          Url::fromRoute('carlson_general.purge_trace_settings'),
        )->toString(),
      ],
    ];

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
      $view_url = Url::fromRoute('carlson_general.purge_trace_view', [
        'date' => $file['date'],
        'filename' => $file['filename'],
      ]);
      $download_url = Url::fromRoute('carlson_general.purge_trace_download', [
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

    return [
      '#markup' => '<pre style="white-space: pre-wrap; overflow-wrap: anywhere;">'
        . Html::escape($contents)
        . '</pre>',
    ];
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

}
