<?php

namespace Drupal\carlson_general\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\File\FileSystemInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Drupal\Core\Url;
use Drupal;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Extension\ModuleExtensionList;

/**
 * Controller for the Carlson Scripts reports page.
 */
class CarlsonScriptsController extends ControllerBase {

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * The module extension list service.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * Constructs a CarlsonScriptsController object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   The module extension list service.
   */
  public function __construct(FileSystemInterface $file_system, ModuleExtensionList $module_extension_list) {
    $this->fileSystem = $file_system;
    $this->moduleExtensionList = $module_extension_list;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Gets the scripts directory path for the current site.
   *
   * @return string
   *   The absolute path to the scripts directory.
   */
  protected function getScriptsDirectory() {
    $module_path = $this->moduleExtensionList->getPath('carlson_general');
    $path = $this->fileSystem->realpath($module_path . '/scripts/');
    \Drupal::logger('carlson_general')->notice('Scripts directory path: @path', ['@path' => $path]);
    return $path;
  }

  /**
   * Builds the Carlson Scripts reports page.
   *
   * @return array
   *   A render array for the Carlson Scripts reports page.
   */
  public function content() {
    $directory = $this->getScriptsDirectory();
    $allowed_extensions = ['txt', 'log', 'csv', 'php'];
    $files = [];

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
            $file_path = "{$directory}/{$file}";
            $basename = pathinfo($file, PATHINFO_FILENAME);
            $files[] = [
              'name' => $file,
              'basename' => $basename,
              'size' => $this->formatSize(filesize($file_path)),
              'modified' => date('Y-m-d H:i:s', filemtime($file_path)),
              'extension' => $extension,
              'path' => $file_path,
            ];
          }
        }
      }
      closedir($handle);
    }

    // Sort files alphabetically by filename
    usort($files, function($a, $b) {
      return strcmp($a['name'], $b['name']);
    });

    $build['files_table'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Filename'),
        $this->t('Size'),
        $this->t('Last Modified'),
        $this->t('Actions'),
      ],
      '#rows' => array_map(function($file) {
        $actions = [];

        // Add view link for all allowed file types
        $actions[] = [
          'data' => [
            '#type' => 'link',
            '#title' => $this->t('View'),
            '#url' => Url::fromRoute('carlson_general.view_file', ['basename' => $file['basename']]),
            '#attributes' => [
              'target' => '_blank',
              'class' => ['button', 'button--small'],
            ],
          ],
        ];

        // Add download link for CSV files
        if ($file['extension'] === 'csv') {
          $actions[] = [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Download'),
              '#url' => Url::fromRoute('carlson_general.download_csv', ['basename' => $file['basename']]),
              '#attributes' => [
                'class' => ['button', 'button--primary', 'button--small'],
              ],
            ],
          ];
        }

        // Add execute link for PHP files
        if ($file['extension'] === 'php') {
          $actions[] = [
            'data' => [
              '#type' => 'link',
              '#title' => $this->t('Execute'),
              '#url' => Url::fromRoute('carlson_general.execute_script', ['basename' => $file['basename']]),
              '#attributes' => [
                'class' => ['button', 'button--primary', 'button--small'],
              ],
            ],
          ];
        }

        return [
          $file['name'],
          $file['size'],
          $file['modified'],
          ['data' => $actions],
        ];
      }, $files),
      '#empty' => $this->t('No files found.'),
    ];

    return $build;
  }

  /**
   * View a file in the browser.
   *
   * @param string $basename
   *   The basename of the file to view (without extension).
   *
   * @return array
   *   A render array for the file view.
   */
  public function viewFile($basename) {
    // Sanitize the basename to prevent directory traversal
    $basename = $this->sanitizeBasename($basename);

    $directory = $this->getScriptsDirectory();
    $allowed_extensions = ['txt', 'log', 'csv', 'php'];
    $file_path = NULL;
    $extension = NULL;

    // Try each allowed extension
    foreach ($allowed_extensions as $ext) {
      $test_path = $directory . $basename . '.' . $ext;
      if (file_exists($test_path)) {
        $file_path = $test_path;
        $extension = $ext;
        break;
      }
    }

    if (!$file_path) {
      throw new NotFoundHttpException('File not found.');
    }

    // File name markup
    $file_name_markup = [
      '#markup' => '<strong>' . $this->t('File:') . ' ' . htmlspecialchars($basename . '.' . $extension) . '</strong>',
    ];

    $download_button = NULL;
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
      case 'php':
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
            'class' => ['csv-script-table'],
            'style' => 'margin-top: 1em;',
          ],
        ];
        $download_button = [
          '#type' => 'link',
          '#title' => $this->t('Download'),
          '#url' => \Drupal\Core\Url::fromRoute('carlson_general.download_csv', ['basename' => $basename]),
          '#attributes' => [
            'class' => ['button', 'button--primary', 'button--small'],
            'style' => 'margin-left: 1em;',
          ],
        ];
        break;
      default:
        throw new NotFoundHttpException('Unsupported file type.');
    }

    $build = [
      'file_name' => $file_name_markup,
    ];
    if ($download_button) {
      $build['download_button'] = $download_button;
    }
    $build['content'] = $content;
    return $build;
  }

  /**
   * Sanitizes a basename to prevent directory traversal.
   *
   * @param string $basename
   *   The basename to sanitize.
   *
   * @return string
   *   The sanitized basename containing only alphanumeric characters, underscores, and dashes.
   */
  protected function sanitizeBasename($basename) {
    // Remove any non-alphanumeric characters except underscores and dashes
    $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $basename);
    return $sanitized;
  }

  /**
   * Execute a PHP script.
   *
   * @param string $basename
   *   The basename of the file to execute (without extension).
   *
   * @return array
   *   A render array containing the script output.
   */
  public function executeScript($basename) {
    // Sanitize the basename to prevent directory traversal
    $basename = $this->sanitizeBasename($basename);

    $scripts_dir = $this->getScriptsDirectory();
    $file_path = "{$scripts_dir}/{$basename}.php";

    // Log the file path for debugging
    \Drupal::logger('carlson_general')->notice('Attempting to execute script: @path', ['@path' => $file_path]);

    if (!file_exists($file_path)) {
      \Drupal::logger('carlson_general')->error('File does not exist: @path', ['@path' => $file_path]);
      throw new NotFoundHttpException('File not found.');
    }

    if (!is_readable($file_path)) {
      \Drupal::logger('carlson_general')->error('File is not readable: @path', ['@path' => $file_path]);
      throw new NotFoundHttpException('File is not readable.');
    }

    try {
      // Include the script directly
      $result_string = include $file_path;
    }
    catch (\Exception $e) {
      ob_end_clean();
      // Log the error with full details
      \Drupal::logger('carlson_general')->error('Error executing script @path: @error in @file on line @line', [
        '@path' => $file_path,
        '@error' => $e->getMessage(),
        '@file' => $e->getFile(),
        '@line' => $e->getLine()
      ]);
      throw $e;
    }

    // Log the result and output
    \Drupal::logger('carlson_general')->notice('Script execution result: Output length: @length', [
      '@length' => strlen($result_string)
    ]);

    if (empty($result_string)) {
      \Drupal::logger('carlson_general')->warning('Script executed: @path', ['@path' => $file_path]);
      $result_string = "Script executed successfully but produced no output.";

      // Add log message link if dblog is enabled
      if (\Drupal::moduleHandler()->moduleExists('dblog')) {
        $log_url = Url::fromRoute('dblog.overview', ['type' => ['carlson_general']]);
        $result_string .= "\n\nCheck the <a href=\"" . $log_url->toString() . "\">Recent log messages</a> for more details.";
      }
    }

    // Convert URLs to links with basename as text
    $result_string = preg_replace_callback(
      '/(https?:\/\/[^\s<>]+)/i',
      function ($matches) {
        $url = $matches[1];
        $basename = basename(parse_url($url, PHP_URL_PATH));
        // If basename is empty (e.g., root URL), use the domain
        if (empty($basename)) {
          $basename = parse_url($url, PHP_URL_HOST);
        }
        return '<a href="' . htmlspecialchars($url) . '" target="_blank">' . htmlspecialchars($basename) . '</a>';
      },
      $result_string
    );

    // Create a response with the script output
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => $result_string,
      '#attributes' => [
        'style' => 'background: #f5f5f5; padding: 15px; border-radius: 5px; overflow: auto;',
      ],
    ];

    return [
      '#type' => 'markup',
      '#markup' => \Drupal::service('renderer')->render($build),
      '#attached' => [
        'library' => ['carlson_general/code_highlight'],
      ],
    ];
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

  /**
   * Download a CSV file from the scripts directory.
   *
   * @param string $basename
   *   The basename of the file to download (without extension).
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response.
   */
  public function downloadCsv($basename) {
    $basename = $this->sanitizeBasename($basename);
    $scripts_dir = $this->getScriptsDirectory();
    $file_path = $scripts_dir . $basename . '.csv';
    if (!file_exists($file_path) || !is_readable($file_path)) {
      throw new NotFoundHttpException('File not found.');
    }
    $response = new \Symfony\Component\HttpFoundation\BinaryFileResponse($file_path);
    $response->setContentDisposition(
      \Symfony\Component\HttpFoundation\ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $basename . '.csv'
    );
    $response->headers->set('Content-Type', 'text/csv');
    return $response;
  }

}
