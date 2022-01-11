<?php

namespace Drupal\carlson_quiz\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\webform\WebformSubmissionInterface;

class QuizController extends ControllerBase {
  function build(NodeInterface $node, WebformSubmissionInterface $webform_submission) {

    $results = [];
//    $cond_result_ids = [];
    $answer_count = 0;
    $data =  $webform_submission->getData();

    foreach ($data as $key => $pid) {
      if(substr($key, 0, 9) == 'question_' && $pid && $pid !== '') {
        if(is_array($pid)) {
          foreach ($pid as $pid_item) {
            $answer = Paragraph::load($pid_item);
            if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
              $answer_results = $answer->get('field_quiz_answer_result')->getValue();
              $answer_count++;
              foreach ($answer_results as $answer_result) {
                $id = $answer_result["target_id"];
                $results[$id] = array_key_exists($id, $results) ? ++$results[$id] : 1;

                //Conditional Results Logic
//                if($answer->hasField('field_quiz_answer_cond_results') && !empty($answer->get('field_quiz_answer_cond_results'))) {
//                  $conditional_results = $answer->get('field_quiz_answer_cond_results')->getValue();
//                  if(!empty($conditional_results) && $conditional_results[0]["value"] == '1') {
//                    $cond_result_ids[$id] = array_key_exists($id, $cond_result_ids) ? ++$cond_result_ids[$id] : 1;
//                  }
//                }
              }
            }
          }
        }
        else {
          $answer = Paragraph::load($pid);
          if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
            $answer_results = $answer->get('field_quiz_answer_result')->getValue();
            $answer_count++;
            foreach ($answer_results as $answer_result) {
              $id = $answer_result["target_id"];
              $results[$id] = array_key_exists($id, $results) ? ++$results[$id] : 1;

              //Conditional Results Logic
//              if($answer->hasField('field_quiz_answer_cond_results') && !empty($answer->get('field_quiz_answer_cond_results'))) {
//                $conditional_results = $answer->get('field_quiz_answer_cond_results')->getValue();
//                if(!empty($conditional_results) && $conditional_results[0]["value"] == '1') {
//                  $cond_result_ids[$id] = array_key_exists($id, $cond_result_ids) ? ++$cond_result_ids[$id] : 1;
//                }
//              }
            }
          }
        }
      }
    }

//    if(!empty($cond_result_ids)) {
//      $results = $cond_result_ids;
//    }

    arsort($results);
    $results = array_filter($results, function($v) use ($answer_count) {
      return $v === $answer_count;
    });

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
