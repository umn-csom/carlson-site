<?php

namespace Drupal\carlson_quiz\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\webform\WebformSubmissionInterface;

class QuizController extends ControllerBase {
  function build(NodeInterface $node, WebformSubmissionInterface $webform_submission) {

    $results = [];
    $data =  $webform_submission->getData();

    foreach ($data as $pid) {
      $answer = Paragraph::load($pid);
      if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
        $answer_results = $answer->get('field_quiz_answer_result')->getValue();
        foreach ($answer_results as $answer_result) {
          $id = $answer_result["target_id"];
          $results[$id] = array_key_exists($id, $results) ? ++$results[$id] : 1;
        }

      }
    }

    arsort($results);

    return [
      '#node' => $node,
      '#results' => $results,
      '#theme' => 'node__quiz__quiz_results',
    ];
  }
}
