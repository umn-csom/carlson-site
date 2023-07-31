<?php

namespace Drupal\carlson_general\Form;

use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\Language;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\redirect\Entity\Redirect;

class CSVImportForm extends FormBase {

  public function getFormId() {
    return 'carlson_general_csv_import';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    // Define a form element for the CSV file upload.
    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Upload the CSV file containing the links.'),
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#upload_location' => 'public://',
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Start Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the uploaded file.
    $file = file_save_upload('csv_file', $form['csv_file']['#upload_validators'])[0];

    if ($file) {
      // Set the temporary file as permanent.
      $file->setPermanent();
      $file->save();

      // Create the batch with the file path as an argument.
      $batch = (new BatchBuilder())
        ->setTitle($this->t('Importing CSV...'))
        ->setFinishCallback([get_class($this), 'batchFinished'])
        ->addOperation([get_class($this), 'batchProcess'], [$file->getFileUri(), 0]);

      batch_set($batch->toArray());
    }
    else {
      \Drupal::messenger()->addError($this->t('Failed to upload the CSV file.'));
    }
  }



  public static function batchProcess($fileUri, $startRow, &$context) {
    // Initialize the sandbox.
    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current_row'] = $startRow;
      $context['sandbox']['max'] = 778; // Your total rows
    }

    // Call your function here.
    (new CSVImportForm)->createLinks($context['sandbox'], $fileUri);

    // Update the finished value from the sandbox to the context.
    $context['finished'] = $context['sandbox']['finished'];
  }

  public static function batchFinished($success, $results, $operations) {
    if ($success) {
      // Add a success message to be displayed in the UI.
      \Drupal::messenger()->addMessage(t('Links have been created. '));
    } else {
      $message = t('Finished with an error.');
      // Add an error message to be displayed in the UI.
      \Drupal::messenger()->addError($message);
    }
    \Drupal::logger('carlson_general')->notice('Import has been finished. ');
  }


  protected function createLinks(&$sandbox, $fileUri) {
    $module_handler = \Drupal::service('module_handler');
    $path = $module_handler->getModule('carlson_general')->getPath();
    $csv = new \SplFileObject($fileUri);

    // Move file pointer to last position.
    for ($i = 0; $i < $sandbox['current_row']; $i++) {
      $csv->fgetcsv();
    }

    // Initialize progress.
    if ($sandbox['progress'] === 0) {
      // Count total lines in the file.
      $csv->rewind();
      $sandbox['max'] = 0;

      while (!$csv->eof()) {
        $line = $csv->fgetcsv();
        // If the line is not false, not null, and contains at least one non-empty element.
        if ($line !== false && $line !== null && !empty(array_filter($line))) {
          $sandbox['max']++;
        }
      }

      $csv->rewind();
    }


    // Process rows.
    $limit = 50; // Process 50 rows per batch run.
    for ($i = 0; $i < $limit && !$csv->eof() && $sandbox['current_row'] < $sandbox['max']; $i++) {
      $row = $csv->fgetcsv(); // Read the current row.

      // Log the current row and iteration.
      \Drupal::logger('carlson_general')->notice('Processing row: ' . print_r($row, TRUE));
      \Drupal::logger('carlson_general')->notice('Iteration: ' . $i);

      if ($sandbox['current_row'] > 0) { // Skip the header row.
        \Drupal::logger('carlson_general')->notice('Max: ' . $sandbox['max']);
        self::carlson_general_create_menu_link($row);
      }

      $sandbox['progress']++;
      $sandbox['current_row']++;
    }

    // Log the final progress and current_row.
    \Drupal::logger('carlson_general')->notice('Final progress: ' . $sandbox['progress']);
    \Drupal::logger('carlson_general')->notice('Final current_row: ' . $sandbox['current_row']);


    $csv = NULL; // Release file handle.

    $sandbox['finished'] = $sandbox['progress'] / $sandbox['max'];
    \Drupal::logger('carlson_general')->notice('Progress: ' . $sandbox['progress'] . ' / ' . $sandbox['max'] . ' = ' . $sandbox['finished']);
    if ($sandbox['finished'] >= 1) {
      return t('Menu links have been imported.');
    }
  }

  /**
   * Function to create a menu link.
   */
  protected function carlson_general_create_menu_link($row) {
    $aliasManager = \Drupal::service('path_alias.manager');
    $entityTypeManager = \Drupal::service('entity_type.manager');
    $state = \Drupal::state();
    $last_parent_mlid_by_level = $state->get('carlson_general_last_parent_mlid_by_level', []);
    $menu_item_weights = $state->get('carlson_general_menu_item_weights', [
      0 => '0',
      1 => '0',
      2 => '0',
      3 => '0',
      4 => '0',
      5 => '0',
      6 => '0',
      7 => '0',
    ]);

    // Allow comparing current menu level with previous menu link level.
    $last_level = 0;

    // Skip the header row and rows where Menu is not 'main'.
    if (isset($row[4]) && $row[4] == 'main') {
      $title = '';
      $parent = '';
      $menu_level = 0;

      // Find the first menu link level title that is not empty.
      for ($i = 1; $i <= 7; $i++) {
        if (!empty($row[$i + 4])) {
          $title = $row[$i + 4];
          $menu_level = $i;

          // Set the parent mlid if we're not at the top level and
          // we find a parent mlid from a previous row.
          if ($i > 1) {
            if (isset($last_parent_mlid_by_level[$i - 1])) {
              $parent = $last_parent_mlid_by_level[$i - 1];
            } else {
              $message = str_repeat("  ", $menu_level) . " no parent found for L$i $title.";
              //\Drupal::logger('carlson_general')->warning($message);
            }
          }
          break;
        }
      }

      // Zero out deeper menu link weights when we go back up a level.
      if ($menu_level < $last_level) {
        $i = $menu_level + 1;
        while ($i < count($menu_item_weights)) {
          $menu_item_weights[$i++] = '0';
        }
      }

      $weight = $menu_item_weights[$menu_level];
      // Increment menu link weight at the current level.
      $menu_item_weights[$menu_level] = $menu_item_weights[$menu_level] + 1;

      $uri = $row[0];
      if (in_array($uri, ['<nolink>', '<button>'])) {
        $uri = 'route:' . $uri;
      } // Update the node with the new title and breadcrumb.
      elseif (preg_match('/^\/node\/(\d+)$/', $uri, $matches)) {
        $nodeId = $matches[1] ?? '';
        $uri = 'internal:/node/' . $nodeId;
        $node = $entityTypeManager->getStorage('node')->load($nodeId);
        if ($node) {
          $node->setTitle($row[2]);
          $node->set('field_breadcrumb_title', $row[3]);

          $newAlias = $row[1];
          $nodePath = '/node/' . $nodeId;

          // Check if the alias already exists for the current path.
          $existingAlias = $aliasManager->getAliasByPath($nodePath);

          // update the existing alias or create a new one
          if ($existingAlias !== $newAlias) {
            $path_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
            $existingPath = $path_storage->loadByProperties(['path' => $nodePath]);

            // If there is an existing alias, update it
            if ($existingPath) {
              $existingPath = reset($existingPath);
              $existingPath->set('alias', $newAlias);
              $existingPath->save();
            } else {
              // If not, create a new one
              $path_alias = \Drupal::entityTypeManager()->getStorage('path_alias')->create([
                'path' => $nodePath,
                'alias' => $newAlias,
              ]);
              $path_alias->save();
            }
            self::createRedirect($existingAlias, $newAlias);
          }

          $node->save();
        } else {
          $message = 'No node found with ID: ' . $nodeId;
          \Drupal::logger('carlson_general')->warning($message);
        }
      } elseif (preg_match('/^\//', $uri)) {
        $uri = 'internal:' . $uri;
      }

      // Create a menu link.
      $menu_link = MenuLinkContent::create([
        'title' => $title,
        'link' => ['uri' => $uri],
        'menu_name' => $row[4],
        'expanded' => TRUE,
        'enabled' => $row[12] == 'FALSE' ? 0 : 1,
        'parent' => $parent,
        'weight' => $weight,
      ]);
      $menu_link->save();
      // Store the parent link id for following children rows.
      $last_parent_mlid_by_level[$menu_level] = $menu_link->getPluginId();
      $last_level = $menu_level;

      //$this->output()->writeln(str_pad(str_repeat(" -", $menu_level) . " $weight $title", 60) . " " . $menu_link->getPluginId() . " " . $uri);
    }

    // At the end of your function, store the updated values back to the state API.
    $state->set('carlson_general_last_parent_mlid_by_level', $last_parent_mlid_by_level);
    $state->set('carlson_general_menu_item_weights', $menu_item_weights);
  }

  protected function createRedirect($oldUrl, $newUrl, $language = Language::LANGCODE_NOT_SPECIFIED) {
    $oldUrl = trim($oldUrl, '/');
    $newUrl = trim($newUrl, '/');

    if ($oldUrl == $newUrl) {
      // Skip creating redirects where the old URL is the same as the new URL.
      return;
    }

    // If no existing redirect, then create a new one.
    if (!self::getExistingRedirect($oldUrl, $newUrl)) {
      $redirect = Redirect::create([
        'redirect_source' => ['path' => $oldUrl],
        'redirect_redirect' => 'internal:/' . $newUrl,
        'language' => $language,
      ]);
      $redirect->save();
    }
  }

  protected function getExistingRedirect($oldUrl, $newUrl) {
    $redirectStorage = \Drupal::entityTypeManager()->getStorage('redirect');

    $oldUrl = trim($oldUrl, '/');
    $newUrl = str_replace('internal:', '', $newUrl);
    $newUrl = trim($newUrl, '/');

    $query = $redirectStorage->getQuery()
      ->condition('redirect_source__path', $oldUrl);
    //->condition('redirect_redirect__uri', 'internal:' . $newUrl);

    $redirects = $query->execute();

    // If the query returns any results, the exact redirect already exists.
    return !empty($redirects) ? $redirectStorage->load(reset($redirects)) : null;
  }

}