<?php

namespace Drupal\carlson_general\Form;

use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a guarded form for clearing the Acquia DEV database.
 */
class DevDatabaseResetForm extends FormBase {

  /**
   * The required confirmation phrase.
   */
  protected const CONFIRMATION_PHRASE = 'DELETE DEV DATABASE';

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * Constructs the form.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('database'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carlson_general_dev_database_reset';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $environment = $this->getAcquiaEnvironment();
    $is_dev = $this->isDevEnvironment();
    $tables = $this->getDatabaseTables();

    $form['#attached']['library'][] = 'carlson_general/dev_database_reset';

    $form['warning'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['messages', 'messages--error']],
      'message' => [
        '#markup' => $this->t(
          '<strong>Danger:</strong> this permanently drops the selected '
          . 'Drupal tables from the current database. The site may stop '
          . 'working until the database is synced again from the management '
          . 'portal.',
        ),
      ],
    ];

    $form['environment'] = [
      '#type' => 'item',
      '#title' => $this->t('Detected Acquia environment'),
      '#plain_text' => $environment ?: $this->t('Not detected'),
    ];

    $form['database_name'] = [
      '#type' => 'item',
      '#title' => $this->t('Database'),
      '#plain_text' => $this->getDatabaseName() ?: $this->t('Not detected'),
    ];

    $form['table_count'] = [
      '#type' => 'item',
      '#title' => $this->t('Drupal-managed tables detected'),
      '#plain_text' => (string) count($tables),
    ];

    $form['tables'] = [
      '#type' => 'details',
      '#title' => $this->t('Tables to be dropped'),
      '#open' => FALSE,
    ];
    if ($tables === []) {
      $form['tables']['empty'] = [
        '#plain_text' => $this->t('No Drupal-managed database tables were found.'),
      ];
    }
    else {
      $prefix = $this->database->getPrefix();
      $options = [];
      foreach ($tables as $table) {
        $options[$table] = $prefix . $table;
      }
      $default_selection = $this->getDefaultSelectedTables($tables, $form_state);

      $form['tables']['selection_wrapper'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dev-database-reset-table-selection']],
      ];
      $form['tables']['selection_wrapper']['actions'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['dev-database-reset-table-actions']],
        'links' => [
          '#markup' => $this->t(
            '<a href="#" class="dev-database-reset-select-all">Select all</a> | '
            . '<a href="#" class="dev-database-reset-clear-selection">Clear selection</a>',
          ),
        ],
      ];
      $form['tables']['selection_wrapper']['selection'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Table names'),
        '#title_display' => 'invisible',
        '#options' => $options,
        '#default_value' => array_combine(
          $default_selection,
          $default_selection,
        ),
        '#disabled' => !$is_dev,
      ];
    }

    if (!$is_dev) {
      $form['blocked'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages', 'messages--warning']],
        'message' => [
          '#plain_text' => $this->t(
            'This form is disabled because it only runs on the Acquia DEV '
            . 'environment.',
          ),
        ],
      ];
    }

    $form['confirmation'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Confirmation phrase'),
      '#description' => $this->t(
        'Type @phrase exactly to drop the selected DEV database tables.',
        ['@phrase' => self::CONFIRMATION_PHRASE],
      ),
      '#required' => TRUE,
      '#disabled' => !$is_dev,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Drop selected database tables'),
      '#button_type' => 'danger',
      '#disabled' => !$is_dev || $tables === [],
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
    if (!$this->isDevEnvironment()) {
      $form_state->setErrorByName(
        'confirmation',
        $this->t('This reset can only run on the Acquia DEV environment.'),
      );
      return;
    }

    $confirmation = (string) $form_state->getValue('confirmation');
    if ($confirmation !== self::CONFIRMATION_PHRASE) {
      $form_state->setErrorByName(
        'confirmation',
        $this->t('The confirmation phrase does not match.'),
      );
    }

    if ($this->getDatabaseTables() === []) {
      $form_state->setErrorByName(
        'confirmation',
        $this->t('No Drupal-managed database tables were found.'),
      );
      return;
    }

    if ($this->getSelectedTables($form_state) === []) {
      $form_state->setErrorByName(
        'tables][selection_wrapper][selection',
        $this->t('Select at least one table to drop.'),
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $tables = $this->getSelectedTables($form_state);
    $this->closeActiveSession();
    $dropped = $this->dropTables($tables);
    $message = sprintf(
      "Dropped %d selected Drupal database tables from the DEV environment.\n"
      . "The site is now expected to be unavailable until the database is "
      . "synced again from the Drupal Management Portal.\n",
      $dropped,
    );

    $response = new Response($message, Response::HTTP_OK, [
      'Content-Type' => 'text/plain; charset=UTF-8',
    ]);
    $form_state->setResponse($response);
  }

  /**
   * Closes the active PHP session before the session table is dropped.
   */
  protected function closeActiveSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_write_close();
    }
  }

  /**
   * Gets selected table names from the form submission.
   *
   * @return string[]
   *   Unprefixed table names selected for dropping.
   */
  protected function getSelectedTables(FormStateInterface $form_state): array {
    $selection = $form_state->getValue([
      'tables',
      'selection_wrapper',
      'selection',
    ]);
    if (!is_array($selection)) {
      return [];
    }

    return array_values(array_filter($selection));
  }

  /**
   * Gets the default checked tables for the checkbox element.
   *
   * @param string[] $tables
   *   All available unprefixed table names.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return string[]
   *   Unprefixed table names that should be checked by default.
   */
  protected function getDefaultSelectedTables(
    array $tables,
    FormStateInterface $form_state,
  ): array {
    $selected = $this->getSelectedTables($form_state);
    if ($selected !== []) {
      return array_values(array_intersect($selected, $tables));
    }

    return $tables;
  }

  /**
   * Gets the active database name from the connection options.
   *
   * @return string
   *   The configured database name, or an empty string when unavailable.
   */
  protected function getDatabaseName(): string {
    $options = $this->database->getConnectionOptions();
    $database = $options['database'] ?? '';

    return is_string($database) ? $database : '';
  }

  /**
   * Gets all Drupal-managed base table names for the active connection.
   *
   * @return string[]
   *   Unprefixed table names.
   */
  protected function getDatabaseTables(): array {
    $tables = $this->database->schema()->findTables('%');
    sort($tables);
    return $tables;
  }

  /**
   * Drops the provided tables.
   *
   * @param string[] $tables
   *   Unprefixed table names to drop.
   *
   * @return int
   *   The number of tables dropped.
   */
  protected function dropTables(array $tables): int {
    $dropped = 0;
    $schema = $this->database->schema();

    $this->database->query('SET FOREIGN_KEY_CHECKS=0');
    try {
      foreach ($tables as $table) {
        if ($schema->dropTable($table)) {
          $dropped++;
        }
      }
    }
    finally {
      $this->database->query('SET FOREIGN_KEY_CHECKS=1');
    }

    return $dropped;
  }

  /**
   * Checks whether the current request is running on Acquia DEV.
   *
   * @return bool
   *   TRUE when the detected environment is dev.
   */
  protected function isDevEnvironment(): bool {
    return $this->getAcquiaEnvironment() === 'dev';
  }

  /**
   * Gets the Acquia environment name.
   *
   * @return string
   *   The detected environment name, or an empty string when unavailable.
   */
  protected function getAcquiaEnvironment(): string {
    $environment = $_ENV['AH_SITE_ENVIRONMENT']
      ?? $_SERVER['AH_SITE_ENVIRONMENT']
      ?? getenv('AH_SITE_ENVIRONMENT');

    return is_string($environment) ? $environment : '';
  }

}
