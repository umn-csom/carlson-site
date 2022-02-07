<?php

namespace Drupal\course_comparison_table\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'Course Comparison Table' Block.
 *
 * @Block(
 *   id = "course_comparison_table_block",
 *   admin_label = @Translation("Course Comparison Table Block"),
 *   category = @Translation("Course Comparison Table Block"),
 * )
 */
class CourseComparisonTableBlock extends BlockBase {

  // /**
  //  * {@inheritdoc}
  //  */
  // public function defaultConfiguration() {
  //   return ['label_display' => FALSE];
  // }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $renderable = [
      '#theme' => 'course_comparison_table',
      '#title' => 'Compare Programs',
      '$description' => 'Compare Programs'
    ];

    return $renderable;
  }

}