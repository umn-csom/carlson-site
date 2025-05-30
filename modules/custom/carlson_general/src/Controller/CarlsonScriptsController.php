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
   * Constructs a CarlsonScriptsController object.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   The file system service.
   */
  public function __construct(FileSystemInterface $file_system) {
    $this->fileSystem = $file_system;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('file_system')
    );
  }

  /**
   * Gets the scripts directory path for the current site.
   *
   * @return string
   *   The absolute path to the scripts directory.
   */
  protected function getScriptsDirectory() {
    $path = \Drupal::root() . '/sites/carlsonschool.umn.edu/modules/custom/carlson_general/scripts/';
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
          $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
          if (in_array($extension, $allowed_extensions)) {
            $file_path = $directory . $file;
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

    // Sort files by modification time, newest first
    usort($files, function($a, $b) {
      return strtotime($b['modified']) - strtotime($a['modified']);
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
   * @return \Symfony\Component\HttpFoundation\Response
   *   The file response.
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

    $response = new BinaryFileResponse($file_path);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_INLINE,
      $basename . '.' . $extension
    );

    // Set appropriate content type based on file extension
    switch ($extension) {
      case 'txt':
        $response->headers->set('Content-Type', 'text/plain');
        break;
      case 'csv':
        $response->headers->set('Content-Type', 'text/csv');
        break;
      case 'log':
        $response->headers->set('Content-Type', 'text/plain');
        break;
      case 'php':
        $response->headers->set('Content-Type', 'text/plain');
        break;
    }

    return $response;
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
    $file_path = $scripts_dir . $basename . '.php';

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

    // Load the script content
    $script_content = file_get_contents($file_path);

    // Start output buffering
    ob_start();
    try {
      // Include the script directly
      include $file_path;
      $output = ob_get_clean();
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
      '@length' => strlen($output)
    ]);

    if (empty($output)) {
      \Drupal::logger('carlson_general')->warning('Script executed: @path', ['@path' => $file_path]);
      $output = "Script executed successfully but produced no output.";

      // Add log message link if dblog is enabled
      if (\Drupal::moduleHandler()->moduleExists('dblog')) {
        $log_url = Url::fromRoute('dblog.overview', ['type' => ['carlson_general']]);
        $output .= "\n\nCheck the <a href=\"" . $log_url->toString() . "\">Recent log messages</a> for more details.";
      }
    }

    // Create a response with the script output
    $build = [
      '#type' => 'html_tag',
      '#tag' => 'pre',
      '#value' => $output,
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

}
