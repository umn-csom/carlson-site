<?php

namespace Drupal\carlson_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for the Carlson Scripts Logs page.
 */
class CarlsonScriptsLogsController extends ControllerBase {

  /**
   * Gets the logs directory path (public://script_logs/).
   *
   * @return string
   *   The absolute path to the logs directory.
   */
  protected function getLogsDirectory() {
    $stream_wrapper = \Drupal::service('stream_wrapper_manager')->getViaScheme('public');
    $path = $stream_wrapper->realpath() . '/script_logs/';
    \Drupal::logger('carlson_general')->notice('Logs directory path: @path', ['@path' => $path]);
    return $path;
  }

  /**
   * Builds the Carlson Scripts Logs page.
   *
   * @return array
   *   A render array for the logs page.
   */
  public function content() {
    $directory = $this->getLogsDirectory();
    $allowed_extensions = ['txt', 'log', 'csv', 'html'];
    $files = [];

    // Get sort and direction from query parameters
    $request = \Drupal::request();
    $sort = $request->query->get('sort', 'name');
    $direction = strtolower($request->query->get('direction', 'asc')) === 'desc' ? 'desc' : 'asc';
    $reverse = $direction === 'desc';

    if (is_dir($directory)) {
      $handle = opendir($directory);
      while (($file = readdir($handle)) !== FALSE) {
        if ($file != '.' && $file != '..') {
          // Exclude files that start with underscore
          if (strpos($file, '_') === 0) {
            continue;
          }
          $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
          if (in_array($extension, $allowed_extensions)) {
            $file_path = $directory . $file;
            $basename = pathinfo($file, PATHINFO_FILENAME);
            // Replace the last occurrence of '.' + extension with '_' + extension for URL
            $url_filename = preg_replace('/\.' . preg_quote($extension, '/') . '$/', '_' . $extension, $file);
            $files[] = [
              'name' => $file,
              'basename' => $basename,
              'size' => filesize($file_path),
              'size_display' => $this->formatSize(filesize($file_path)),
              'modified' => filemtime($file_path),
              'modified_display' => date('Y-m-d H:i:s', filemtime($file_path)),
              'extension' => $extension,
              'path' => $file_path,
              'url_filename' => $url_filename,
            ];
          }
        }
      }
      closedir($handle);
    }

    // Sorting logic
    $sort_map = [
      'name' => 'name',
      'size' => 'size',
      'modified' => 'modified',
    ];
    $sort_key = isset($sort_map[$sort]) ? $sort_map[$sort] : 'name';
    usort($files, function($a, $b) use ($sort_key, $reverse) {
      if ($a[$sort_key] == $b[$sort_key]) return 0;
      if ($reverse) {
        return ($a[$sort_key] < $b[$sort_key]) ? 1 : -1;
      } else {
        return ($a[$sort_key] > $b[$sort_key]) ? 1 : -1;
      }
    });

    // Helper to build sort links
    $buildSortLink = function($label, $field) use ($sort, $direction) {
      $new_direction = ($sort === $field && $direction === 'asc') ? 'desc' : 'asc';
      $url = Url::fromRoute('carlson_general.carlson_scripts_logs', [], [
        'query' => [
          'sort' => $field,
          'direction' => $new_direction,
        ],
      ]);
      $arrow = '';
      if ($sort === $field) {
        $arrow = $direction === 'asc' ? ' ▲' : ' ▼';
      }
      return [
        '#type' => 'link',
        '#title' => $label . $arrow,
        '#url' => $url,
        '#attributes' => [
          'class' => ['sortable-header'],
        ],
      ];
    };

    $build['files_table'] = [
      '#type' => 'table',
      '#header' => [
        ['data' => $buildSortLink($this->t('Filename'), 'name')],
        ['data' => $buildSortLink($this->t('Size'), 'size')],
        ['data' => $buildSortLink($this->t('Last Modified'), 'modified')],
        $this->t('Actions'),
      ],
      '#rows' => array_map(function($file) {
        $actions = [];
        // View link
        $actions[] = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('View'),
            '#url' => Url::fromRoute('carlson_general.logs_view_file', ['filename' => $file['url_filename']]),
            '#attributes' => [
              'target' => '_blank',
              'class' => ['button', 'button--small'],
            ],
          ],
        ];
        // Download link
        $actions[] = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('Download'),
            '#url' => Url::fromRoute('carlson_general.logs_download_file', ['filename' => $file['url_filename']]),
            '#attributes' => [
              'class' => ['button', 'button--primary', 'button--small'],
            ],
          ],
        ];
        return [
          $file['name'],
          $file['size_display'],
          $file['modified_display'],
          ['data' => $actions],
        ];
      }, $files),
      '#empty' => $this->t('No log files found.'),
    ];

    return $build;
  }

  /**
   * View a log file in the browser.
   *
   * @param string $filename
   *   The file name to view (with extension).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response.
   */
  public function viewFile($filename) {
    $allowed_extensions = ['txt', 'log', 'csv', 'html'];
    $filename = $this->urlToRealFilename($filename, $allowed_extensions);
    $filename = $this->sanitizeFilename($filename);
    $directory = $this->getLogsDirectory();
    $file_path = $directory . $filename;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions) || !file_exists($file_path)) {
      throw new NotFoundHttpException('File not found.');
    }

    // Download button render array
    $download_button = [
      '#type' => 'link',
      '#title' => $this->t('Download'),
      '#url' => \Drupal\Core\Url::fromRoute('carlson_general.logs_download_file', ['filename' => $filename]),
      '#attributes' => [
        'class' => ['button', 'button--primary', 'button--small'],
        'style' => 'margin-left: 1em;',
      ],
    ];

    // File name markup
    $file_name_markup = [
      '#markup' => '<strong>' . $this->t('Log file:') . ' ' . htmlspecialchars($filename) . '</strong>',
    ];

    $content = NULL;
    switch ($extension) {
      case 'txt':
      case 'log':
        $contents = file_get_contents($file_path);
        $content = [
          '#type' => 'html_tag',
          '#tag' => 'pre',
          '#value' => $contents,
          '#attributes' => [
            'style' => 'background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto;',
          ],
        ];
        break;
      case 'html':
        $contents = file_get_contents($file_path);
        $content = [
          '#markup' => $contents,
        ];
        break;
      case 'csv':
        $rows = [];
        if (($handle = fopen($file_path, 'r')) !== FALSE) {
          while (($data = fgetcsv($handle)) !== FALSE) {
            $rows[] = $data;
          }
          fclose($handle);
        }
        if (empty($rows)) {
          $header = [];
        } else {
          $header = array_shift($rows);
        }
        $content = [
          '#type' => 'table',
          '#header' => $header,
          '#rows' => $rows,
          '#attributes' => [
            'class' => ['csv-log-table'],
            'style' => 'margin-top: 1em;',
          ],
        ];
        break;
      default:
        throw new NotFoundHttpException('Unsupported file type.');
    }

    return [
      'file_name' => $file_name_markup,
      'download_button' => $download_button,
      'content' => $content,
    ];
  }

  /**
   * Download a log file.
   *
   * @param string $filename
   *   The file name to download (with extension).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response.
   */
  public function downloadFile($filename) {
    $allowed_extensions = ['txt', 'log', 'csv', 'html'];
    $filename = $this->urlToRealFilename($filename, $allowed_extensions);
    $filename = $this->sanitizeFilename($filename);
    $directory = $this->getLogsDirectory();
    $file_path = $directory . $filename;
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($extension, $allowed_extensions) || !file_exists($file_path)) {
      throw new NotFoundHttpException('File not found.');
    }
    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename
    );
    switch ($extension) {
      case 'txt':
      case 'html':
      case 'log':
        $response->headers->set('Content-Type', 'text/plain');
        break;
      case 'csv':
        $response->headers->set('Content-Type', 'text/csv');
        break;
    }
    return $response;
  }

  /**
   * Sanitizes a filename to prevent directory traversal.
   *
   * @param string $filename
   *   The filename to sanitize.
   *
   * @return string
   *   The sanitized filename containing only allowed characters.
   */
  protected function sanitizeFilename($filename) {
    // Remove any non-alphanumeric, underscore, dash, or dot characters
    $sanitized = preg_replace('/[^a-zA-Z0-9_.-]/', '', $filename);
    return $sanitized;
  }

  /**
   * Formats a file size in bytes to a human-readable format.
   *
   * @param int $bytes
   *   The size in bytes.
   *
   * @return string
   *   The formatted size.
   */
  protected function formatSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
  }

  protected function urlToRealFilename($url_filename, $allowed_extensions) {
    // Try each allowed extension, replace last _ext with .ext
    foreach ($allowed_extensions as $ext) {
      if (preg_match('/_' . preg_quote($ext, '/') . '$/', $url_filename)) {
        return preg_replace('/_' . preg_quote($ext, '/') . '$/', '.' . $ext, $url_filename);
      }
    }
    return $url_filename;
  }

}
