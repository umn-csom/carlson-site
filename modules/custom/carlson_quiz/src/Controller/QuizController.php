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

    foreach ($data as $key => $pid) {
      if(substr($key, 0, 9) == 'question_' && $pid && $pid !== '') {
        if(is_array($pid)) {
          foreach ($pid as $pid_item) {
            $answer = Paragraph::load($pid_item);
            if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
              $answer_results = $answer->get('field_quiz_answer_result')->getValue();
              foreach ($answer_results as $answer_result) {
                $id = $answer_result["target_id"];
                $results[$id] = array_key_exists($id, $results) ? ++$results[$id] : 1;
              }
            }
          }
        }
        else {
          $answer = Paragraph::load($pid);
          if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
            $answer_results = $answer->get('field_quiz_answer_result')->getValue();
            foreach ($answer_results as $answer_result) {
              $id = $answer_result["target_id"];
              $results[$id] = array_key_exists($id, $results) ? ++$results[$id] : 1;
            }
          }
        }
      }
    }

    $questions = $node->get('field_quiz_questions')->referencedEntities();
    foreach ($questions as $question) {
      if($question->hasField('field_quiz_question_cond_results') && !empty($question->get('field_quiz_question_cond_results'))) {
        $conditional_results = $question->get('field_quiz_question_cond_results')->getValue();
        if(!empty($conditional_results) && $conditional_results[0]["value"] == '1' && !array_key_exists('question_'.$question->id(), $data)) {
          $answers = $question->get('field_quiz_question_answers')->referencedEntities();
          foreach ($answers as $answer_key => $answer) {
            if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
              $answer_results = $answer->get('field_quiz_answer_result')->getValue();
              foreach ($answer_results as $answer_result) {
                $id = $answer_result["target_id"];
                unset($results[$id]);
              }
            }
          }
        }
      }
    }

    arsort($results);

    $tags_from_node = metatag_get_tags_from_route($node);

    $allowed_tags = [
      'description',
      'og_description'
    ];

    $tags = array_filter($tags_from_node["#attached"]["html_head"], function($tag) use ($allowed_tags) {
      return in_array($tag[1], $allowed_tags);
    });

    return [
      '#node' => $node,
      '#webform_submission' => $webform_submission,
      '#results' => $results,
      '#theme' => 'node__quiz__quiz_results',
      '#attached' => [
        'html_head' => $tags,
      ],
    ];
  }

  function getTitle(NodeInterface $node, WebformSubmissionInterface $webform_submission) {
    return $node->label().' Results #'.$webform_submission->serial();
  }
}
