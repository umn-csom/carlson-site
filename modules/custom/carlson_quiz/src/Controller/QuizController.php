<?php

namespace Drupal\carlson_quiz\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\webform\WebformSubmissionInterface;

class QuizController extends ControllerBase
{
    function build(NodeInterface $node, WebformSubmissionInterface $webform_submission)
    {

        $result_ids = [];
        $default_result_ids = [];
        $max_result_ids = [];
        //    $cond_result_ids = [];
        $data =  $webform_submission->getData();

        $questions = $node->get('field_quiz_questions')->referencedEntities();
        foreach ($questions as $question_key => $question) {
            $answers = $question->get('field_quiz_question_answers')->referencedEntities();
            $type = $question->hasField('field_quiz_question_type') && $question->get('field_quiz_question_type')->value === 'multiple' ? 'multiple' : 'single';
            $added = [];
            foreach ($answers as $answer_key => $answer) {
                if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
                    $answer_results = $answer->get('field_quiz_answer_result')->getValue();
                    foreach ($answer_results as $answer_result) {
                        $id = $answer_result["target_id"];
                        if(!in_array($id, $added)) {
                            $default_result_ids[$id] = array_key_exists($id, $default_result_ids) ? ++$default_result_ids[$id] : 1;
                            $added[] = $id;
                        }
                    }
                }
            }
        }


        foreach ($data as $key => $pid) {
            if(substr($key, 0, 9) == 'question_' && $pid && $pid !== '') {
                if(is_array($pid)) {
                    $added = [];
                    foreach ($pid as $pid_item) {
                        $answer = Paragraph::load($pid_item);
                        if(isset($answer) && $answer->hasField('field_quiz_answer_result') && !empty($answer->get('field_quiz_answer_result'))) {
                              $answer_results = $answer->get('field_quiz_answer_result')->getValue();
                            foreach ($answer_results as $answer_result) {
                                $id = $answer_result["target_id"];
                                $result_ids[$id] = array_key_exists($id, $result_ids) ? ++$result_ids[$id] : 1;
                                if(!in_array($id, $added)) {
                                          $max_result_ids[$id] = array_key_exists($id, $max_result_ids) ? ++$max_result_ids[$id] : 1;
                                          $added[] = $id;
                                }

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
                        foreach ($answer_results as $answer_result) {
                            $id = $answer_result["target_id"];
                            $result_ids[$id] = array_key_exists($id, $result_ids) ? ++$result_ids[$id] : 1;
                            $max_result_ids[$id] = array_key_exists($id, $max_result_ids) ? ++$max_result_ids[$id] : 1;

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

        $result_ids = array_filter(
            $result_ids, function ($v, $k) use ($default_result_ids, $max_result_ids) {
                return $max_result_ids[$k] >= $default_result_ids[$k];
            }, ARRAY_FILTER_USE_BOTH
        );
        arsort($result_ids);

        $tags_from_node = metatag_get_tags_from_route($node);

        $allowed_tags = [
        'description',
        'og_description'
        ];

        $tags = array_filter(
            $tags_from_node["#attached"]["html_head"], function ($tag) use ($allowed_tags) {
                return in_array($tag[1], $allowed_tags);
            }
        );

        return [
        '#node' => $node,
        '#webform_submission' => $webform_submission,
        '#results' => $result_ids,
        '#theme' => 'node__quiz__quiz_results',
        '#attached' => [
          'html_head' => $tags,
        ],
        ];
    }

    function getTitle(NodeInterface $node, WebformSubmissionInterface $webform_submission)
    {
        return $node->label().' Results #'.$webform_submission->serial();
    }
}
