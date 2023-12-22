<?php

namespace Drupal\charts_field_formatter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FieldItemListInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Component\Uuid\Php;

/**
 * Plugin implementation of the 'html_inject_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "charts_field_formatter",
 *   label = @Translation("Charts"),
 *   field_types = {
 *     "tablefield"
 *   }
 * )
 */
class ChartsFieldFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  protected $messenger;
  protected $uuidService;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings,  MessengerInterface $messenger, Php $uuidService) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
    $this->messenger = $messenger;
    $this->uuidService = $uuidService;
  }

  /**
   * Creates an instance of the plugin.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container to pull out services used in the plugin.
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   *
   * @return static
   *   Returns an instance of this plugin.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('messenger'),
      $container->get('uuid')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = [];
    $categories = [];

    $charts_settings = $this->config('charts.settings');
    $library = $charts_settings->get('charts_default_settings.library');
    $colors = $charts_settings->get('charts_default_settings.display.colors');
    $highchartsConfig = \Drupal::config('charts_highcharts.settings')->get();
    if (empty($library)) {
      $this->messenger->addError($this->t('You need to first configure Charts default settings'));
      return [];
    }

    foreach ($items as $delta => $item) {
      // Get the entity.
      $entity = $item->getEntity();
      $title = (!is_null($entity->title)) ? $entity->title->value : '';
      // Check if there field_chart_type in the entity then set the chart type of its value
      // Otherwise set it from 'Default chart configuration' in /admin/config/content/charts
      if ($entity->hasField('field_chart_type') && $entity->get('field_chart_type')->value != '') {
        $chart_type = $entity->get('field_chart_type')->value;
      }
      else {
        $chart_type = $charts_settings->get('charts_default_settings.type');
      }

      if (!empty($item->value)) {
        $tabledata = $item->value;
        // No need for tabledata caption
        if (isset($tabledata['caption'])) {
          unset($tabledata['caption']);
        }
        $total_rows = sizeof($tabledata);
        $total_cols = sizeof($tabledata[0]);

        //Handle the CSV column by column to match Highcharts pattern
        for ($col = 0; $col < $total_cols; $col++) {
          $data = [];

          for ($row = 0; $row < $total_rows; $row++) {
            if ($col == 0) {
              //The first column of the CSV file is the categories
              $categories[] = $tabledata[$row][$col];
            }
            else {
              if ($row == 0) {
                //The series name is the first cell in the column
                $series_name = $tabledata[$row][$col];
              }
              elseif ($tabledata[$row][$col] != '') {
                // Remove commas from numeric strings
                if(preg_match("/^[0-9,]+$/", $tabledata[$row][$col])) $tabledata[$row][$col] = str_replace(',', '',  $tabledata[$row][$col]);
                //Handle the minus values and make sure the value is stored as number
                if (substr($tabledata[$row][$col], -1) == '-' || substr($tabledata[$row][$col], 1) == '-') {
                  $tabledata[$row][$col] = floatval($tabledata[$row][$col]) * -1;
                }
                else {
                  $tabledata[$row][$col] = floatval($tabledata[$row][$col]) * 1;
                }
                $data[] = $tabledata[$row][$col];
              }
            }
          }

          //The first column is not a series data, it's the categories
          if ($col == 0) {
            $xaxis_title = array_shift($categories);
            continue;
          }

          //Handle the 'Pie' chart type because it's only accept 2 columns
          if ($chart_type == 'pie' && $col > 1) {
            break;
          }

          $series_data[] = [
            'name' => $series_name,
            'color' => $colors[$col - 1],
            'type' => $chart_type,
            'data' => $data,
          ];
        }
      }

      // Customize options here.
      $options = [
        'type' => $chart_type,
        'title' => $title,
        'xaxis_title' => $xaxis_title,
        'yaxis_title' => '',
        'data_labels'=> $charts_settings->get('charts_default_settings.display.data_labels'),
        'yaxis_min' => '',
        'yaxis_max' => '',
        'three_dimensional' => FALSE,
        'title_position' => $charts_settings->get('charts_default_settings.display.title_position'),
        'legend_position' => $charts_settings->get('charts_default_settings.display.legend_position'),
        'legend_layout' => $highchartsConfig['legend_layout'],
        'legend_background_color' => $highchartsConfig['legend_background_color'],
        'legend_border_width' => $highchartsConfig['legend_border_width'],
        'legend_shadow' => $highchartsConfig['legend_shadow'],
        'grouping'   => FALSE,
        'data_markers'   => $charts_settings->get('charts_default_settings.display.data_markers'),
        'colors'   => $colors,
        'yaxis_prefix'   => $charts_settings->get('charts_default_settings.yaxis.prefix'),
        'yaxis_suffix'   => $charts_settings->get('charts_default_settings.yaxis.suffix'),
        'red_from'   => $charts_settings->get('charts_default_settings.display.gauge.red_from'),
        'red_to'   => $charts_settings->get('charts_default_settings.display.gauge.red_to'),
        'yellow_from'   => $charts_settings->get('charts_default_settings.display.gauge.yellow_from'),
        'yellow_to'   => $charts_settings->get('charts_default_settings.display.gauge.yellow_to'),
        'green_from'   => $charts_settings->get('charts_default_settings.display.gauge.green_from'),
        'green_to'   => $charts_settings->get('charts_default_settings.display.gauge.green_to'),
        'tooltips' => $charts_settings->get('charts_default_settings.display.tooltips')
      ];

      $chart_id = 'chart-' . $this->uuidService->generate();
      // Uses the chart-js.thml.twig template.
      $element[$delta] = [
        '#theme' => 'charts_field_formatter',
        '#library' => (string) $library,
        '#categories' => $categories,
        '#seriesData' => $series_data,
        '#options' => $options,
        '#id' => $chart_id,
        '#override' => [],
      ];
    }

    return $element;
  }

}
