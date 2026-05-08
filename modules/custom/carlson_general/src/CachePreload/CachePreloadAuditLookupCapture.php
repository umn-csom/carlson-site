<?php

namespace Drupal\carlson_general\CachePreload;

use Drupal\carlson_general\EventSubscriber\CachePreloadAuditRequestSubscriber;

/**
 * Captures cachetags lookup groups from warmed anonymous HTTP requests.
 *
 * This is the closest local signal for preload usefulness. Response headers
 * show the final page dependency summary; lookup groups show which tag sets
 * Drupal actually validated while serving one warm request.
 */
final class CachePreloadAuditLookupCapture {

  /**
   * Lookup group analyzer.
   *
   * @var \Drupal\carlson_general\CachePreload\CachePreloadAuditLookupAnalyzer
   */
  private CachePreloadAuditLookupAnalyzer $analyzer;

  /**
   * Constructs a lookup capture helper.
   */
  public function __construct() {
    $this->analyzer = new CachePreloadAuditLookupAnalyzer();
  }

  /**
   * Capture cachetags queries for selected paths.
   *
   * @param array $paths
   *   Paths keyed by path with source labels as values.
   * @param string $base_url
   *   Base URL to fetch.
   * @param int $warmups
   *   Number of warmup requests before query capture.
   * @param string $mysql_user
   *   MySQL user with permission to toggle the local general log.
   * @param string $mysql_pass
   *   MySQL password.
   * @param array $effective_preload_tags
   *   Tags already preloaded by core defaults plus site settings.
   * @param array $request_preload_tags
   *   Extra tags to preload only for this audit request.
   *
   * @return array
   *   Lookup capture summary.
   */
  public function capture(
    array $paths,
    string $base_url,
    int $warmups,
    string $mysql_user,
    string $mysql_pass,
    array $effective_preload_tags,
    array $request_preload_tags = []
  ): array {
    $report = [
      'enabled' => TRUE,
      'paths' => [],
      'unsupported' => '',
      'request_preload_tags' => $request_preload_tags,
    ];

    $request_headers = [];
    if ($request_preload_tags) {
      $token = bin2hex(random_bytes(16));
      \Drupal::state()->set(
        CachePreloadAuditRequestSubscriber::TOKEN_STATE_KEY,
        $token
      );
      $request_headers = [
        CachePreloadAuditRequestSubscriber::TOKEN_HEADER => $token,
        CachePreloadAuditRequestSubscriber::TAGS_HEADER =>
          implode(',', $request_preload_tags),
      ];
    }

    try {
      $pdo = $this->rootConnection($mysql_user, $mysql_pass);
      $pdo->exec("SET GLOBAL log_output = 'TABLE'");
      $pdo->exec('SET GLOBAL general_log = OFF');
    }
    catch (\Throwable $exception) {
      $report['enabled'] = FALSE;
      $report['unsupported'] = 'Warm lookup capture unavailable: '
        . $exception->getMessage();
      if ($request_preload_tags) {
        \Drupal::state()->delete(
          CachePreloadAuditRequestSubscriber::TOKEN_STATE_KEY
        );
      }
      return $report;
    }

    try {
      foreach ($paths as $path => $source) {
        $report['paths'][] = $this->capturePath(
          $pdo,
          $path,
          $source,
          $base_url,
          $warmups,
          $effective_preload_tags,
          $request_headers
        );
      }
    }
    finally {
      try {
        $pdo->exec('SET GLOBAL general_log = OFF');
      }
      catch (\Throwable $exception) {
        // Keep reporting the captured data; this cleanup failure is local-only.
      }
      if ($request_preload_tags) {
        \Drupal::state()->delete(
          CachePreloadAuditRequestSubscriber::TOKEN_STATE_KEY
        );
      }
    }

    return $report;
  }

  /**
   * Capture one warmed path.
   *
   * @param \PDO $pdo
   *   Root MySQL connection.
   * @param string $path
   *   Local path to request.
   * @param string $source
   *   Path source label.
   * @param string $base_url
   *   Base URL to fetch.
   * @param int $warmups
   *   Number of warmup requests.
   * @param array $effective_preload_tags
   *   Tags already preloaded.
   * @param array $request_headers
   *   Extra headers to send with the audit request.
   *
   * @return array
   *   Captured path row.
   */
  private function capturePath(
    \PDO $pdo,
    string $path,
    string $source,
    string $base_url,
    int $warmups,
    array $effective_preload_tags,
    array $request_headers
  ): array {
    $row = [
      'path' => $path,
      'source' => $source,
      'status' => 0,
      'cache' => '',
      'dynamic_cache' => '',
      'query_count' => 0,
      'lookup_group_count' => 0,
      'largest_group_tag_count' => 0,
      'candidate_tags' => [],
      'repeated_preloaded_tags' => [],
      'repeated_stable_tags' => [],
      'groups' => [],
      'recommendation' => '',
      'request_preload_tags' => [],
    ];

    try {
      for ($i = 0; $i < max(0, $warmups); $i++) {
        $this->request($base_url, $path, $request_headers);
      }

      $pdo->exec('SET GLOBAL general_log = OFF');
      $pdo->exec('TRUNCATE TABLE mysql.general_log');
      $pdo->exec('SET GLOBAL general_log = ON');
      try {
        $response = $this->request($base_url, $path, $request_headers);
      }
      finally {
        $pdo->exec('SET GLOBAL general_log = OFF');
      }

      $row['status'] = $response['status'];
      $row['cache'] = $response['cache'];
      $row['dynamic_cache'] = $response['dynamic_cache'];
      $row['request_preload_tags'] = $response['request_preload_tags'];

      $queries = $this->cachetagsQueries($pdo);
      $groups = $this->analyzer->lookupGroups(
        $queries,
        $effective_preload_tags
      );
      $row = array_merge(
        $row,
        $this->analyzer->summarizeGroups($groups, $effective_preload_tags)
      );
    }
    catch (\Throwable $exception) {
      $row['error'] = $exception->getMessage();
      try {
        $pdo->exec('SET GLOBAL general_log = OFF');
      }
      catch (\Throwable $cleanup_exception) {
      }
    }

    return $row;
  }

  /**
   * Fetch one path without cookies.
   *
   * @param string $base_url
   *   Base URL.
   * @param string $path
   *   Local path.
   * @param array $request_headers
   *   Extra request headers.
   *
   * @return array
   *   Response summary.
   */
  private function request(
    string $base_url,
    string $path,
    array $request_headers = []
  ): array {
    $headers = ['User-Agent' => 'CSM-226 cache lookup capture'];
    $headers += $request_headers;
    $response = \Drupal::httpClient()->request('GET', $base_url . $path, [
      // Keep lookup evidence scoped to this URL. Following redirects can mix
      // the redirect response and final page into one capture window.
      'allow_redirects' => FALSE,
      'http_errors' => FALSE,
      'timeout' => 30,
      'verify' => FALSE,
      'headers' => $headers,
    ]);

    return [
      'status' => $response->getStatusCode(),
      'cache' => $response->getHeaderLine('x-drupal-cache'),
      'dynamic_cache' => $response->getHeaderLine('x-drupal-dynamic-cache'),
      'request_preload_tags' => $request_headers[
        CachePreloadAuditRequestSubscriber::TAGS_HEADER
      ] ?? '',
    ];
  }

  /**
   * Read cachetags queries captured by MariaDB's general log.
   *
   * @param \PDO $pdo
   *   Root MySQL connection.
   *
   * @return array
   *   SQL query strings.
   */
  private function cachetagsQueries(\PDO $pdo): array {
    $statement = $pdo->query(
      "SELECT argument
       FROM mysql.general_log
       WHERE command_type = 'Query'
         AND argument LIKE 'SELECT%cachetags%WHERE%tag%IN%'
       ORDER BY event_time, thread_id"
    );

    return $statement ? $statement->fetchAll(\PDO::FETCH_COLUMN) : [];
  }

  /**
   * Open a local root MySQL connection for general-log capture.
   *
   * @param string $mysql_user
   *   MySQL user.
   * @param string $mysql_pass
   *   MySQL password.
   *
   * @return \PDO
   *   Root connection.
   */
  private function rootConnection(
    string $mysql_user,
    string $mysql_pass
  ): \PDO {
    $options = \Drupal::database()->getConnectionOptions();
    $host = $options['host'] ?? 'db';
    $port = $options['port'] ?? 3306;
    $dsn = "mysql:host={$host};port={$port};dbname=mysql;charset=utf8mb4";

    return new \PDO($dsn, $mysql_user, $mysql_pass, [
      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
      \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    ]);
  }

}
