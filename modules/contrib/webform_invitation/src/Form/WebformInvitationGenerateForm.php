<?php

namespace Drupal\webform_invitation\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\webform\WebformInterface;
use Drupal\webform_invitation\InvitationCodes;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form to generate invitation codes.
 */
class WebformInvitationGenerateForm extends FormBase {

  use MessengerTrait;

  /**
   * The invitation codes service.
   *
   * @var \Drupal\webform_invitation\InvitationCodes
   */
  protected $invitationCodes;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'webform_invitation_generate_form';
  }

  /**
   * Constructs a new WebformInvitationGenerateForm instance.
   *
   * @param \Drupal\webform_invitation\InvitationCodes $invitation_codes
   *   The invitation codes service.
   */
  public function __construct(InvitationCodes $invitation_codes) {
    $this->invitationCodes = $invitation_codes;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webform_invitation.invitation_codes')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, WebformInterface $webform = NULL) {

    $form['webform_invitation'] = [
      '#type' => 'details',
      '#title' => $this->t('Webform Invitation'),
      '#open' => TRUE,
    ];
    $form['webform_invitation']['number'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of codes to generate'),
      '#min' => 1,
      '#default_value' => 25,
      '#required' => TRUE,
    ];
    $form['webform_invitation']['type'] = [
      '#type' => 'radios',
      '#title' => $this->t('Type of tokens'),
      '#options' => [
        'md5' => $this->t('MD5 hash (32 characters)'),
        'custom' => $this->t('Custom'),
      ],
      '#default_value' => 'md5',
      '#required' => TRUE,
    ];
    $form['webform_invitation']['length'] = [
      '#type' => 'number',
      '#title' => $this->t('Length of tokens (number of characters)'),
      '#min' => 5,
      '#max' => 64,
      '#default_value' => 32,
      '#required' => TRUE,
      '#states' => [
        'invisible' => [
          ':input[name="type"]' => [
            'value' => 'md5',
          ],
        ],
      ],
    ];
    $form['webform_invitation']['chars'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Characters to be used for tokens'),
      '#options' => [
        1 => $this->t('Lower case letters (a-z)'),
        2 => $this->t('Upper case letters (A-Z)'),
        3 => $this->t('Digits (0-9)'),
        4 => $this->t('Punctuation (.,:;-_!?)'),
        5 => $this->t('Special characters (#+*=$%&|)'),
      ],
      '#default_value' => [1, 2, 3],
      '#required' => TRUE,
      '#states' => [
        'invisible' => [
          ':input[name="type"]' => [
            'value' => 'md5',
          ],
        ],
      ],
    ];
    $form['webform'] = [
      '#type' => 'value',
      '#value' => $webform,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\webform\Entity\Webform $webform */
    $webform = $form_state->getValue('webform');
    $webform_id = $webform->id();

    // Prepare character set for custom code.
    $chars = $form_state->getValue('chars');
    $set = '';
    if (!empty($chars[1])) {
      $set .= 'abcdefghijklmnopqrstuvwxyz';
    }
    if (!empty($chars[2])) {
      $set .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    }
    if (!empty($chars[3])) {
      $set .= '0123456789';
    }
    if (!empty($chars[4])) {
      $set .= '.,:;-_!?';
    }
    if (!empty($chars[5])) {
      $set .= '#+*=$%&|';
    }

    $result = $this->invitationCodes->generate(
      $webform_id,
      $form_state->getValue('number'),
      $form_state->getValue('type'),
      $form_state->getValue('length'),
      $set
    );

    if ($result['error']) {
      $this->messenger()
        ->addMessage($this->t('Due to unique constraint, only @count codes have been generated.', [
          '@count' => $result['count'],
        ]), 'error');
    }
    elseif ($result['count'] == 1) {
      $this->messenger()
        ->addMessage($this->t('A single code has been generated.'));
    }
    else {
      $this->messenger()
        ->addMessage($this->t('A total of @count codes has been generated.', [
          '@count' => $result['count'],
        ]));
    }

    // Redirect user to list of codes.
    $form_state->setRedirect('entity.webform.invitation_codes', [
      'webform' => $webform_id,
    ]);
  }

}
