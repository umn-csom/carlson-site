<?php

namespace Drupal\user_expire\Hook;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\user\Entity\User;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Utility\Token;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hook implementations for user_expire.
 */
class UserExpireHooks {
  use StringTranslationTrait;

  /**
   * Constructs a UserExpireHooks object.
   */
  public function __construct(
    #[Autowire(service: 'database')]
    private readonly Connection $database,
    #[Autowire(service: 'current_user')]
    private readonly AccountProxyInterface $currentUser,
    #[Autowire(service: 'config.factory')]
    private readonly ConfigFactoryInterface $configFactory,
    #[Autowire(service: 'token')]
    private readonly Token $token,
    #[Autowire(service: 'language_manager')]
    private readonly LanguageManagerInterface $languageManager,
    #[Autowire(service: 'date.formatter')]
    private readonly DateFormatterInterface $dateFormatter,
    #[Autowire(service: 'datetime.time')]
    private readonly TimeInterface $time,
  ) {
  }

  /**
   * Implements hook_help().
   */
  #[Hook('help')]
  public function help($route_name, RouteMatchInterface $route_match) {
    $output = '';
    switch ($route_name) {
      // Main module help for the user_expire module.
      case 'help.page.user_expire':
        $output = '<h3>' . $this->t('About') . '</h3>';
        $output .= '<dt>' . $this->t('This module allows an administrator to define a date on which to expire a specific user account or to define a period at a role level where inactive accounts will be locked.') . '</dt>';
        $output .= '<h3>' . $this->t('Uses') . '</h3>';
        $output .= '<dt>' . $this->t('User expire settings.') . '</dt>';
        $output .= '<dd>' . $this->t('This module has a configuration page, see the <a href=":user_expire">User Expire settings</a>.', [
          ':user_expire' => Url::fromRoute('user_expire.admin')->toString(),
        ]) . '</dd>';
        break;
    }
    return $output;
  }

  /**
   * Implements hook_user_load().
   */
  #[Hook('user_load')]
  public function userLoad($users): void {
    foreach ($users as $uid => $user) {
      $query = $this->database->select('user_expire', 'ue');
      $expiration = $query->condition('ue.uid', $uid)->fields('ue', [
        'expiration',
      ])->execute()->fetchField();
      if (!empty($expiration)) {
        $user->expiration = $expiration;
      }
    }
  }

  /**
   * Implements hook_user_login().
   */
  #[Hook('user_login')]
  public function userLogin($account): void {
    user_expire_notify_user();
  }

  /**
   * Implements hook_user_cancel().
   */
  #[Hook('user_cancel')]
  public function userCancel($edit, $account, $method): void {
    user_expire_set_expiration($account);
  }

  /**
   * Implements hook_user_delete().
   */
  #[Hook('user_delete')]
  public function userDelete($account): void {
    user_expire_set_expiration($account);
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Add the user expire form to an individual user's account page.
   *
   * @see \Drupal\user\ProfileForm::form()
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(&$form, FormStateInterface $form_state): void {
    if ($this->currentUser->hasPermission('set user expiration')) {
      $entity = $form_state->getFormObject()->getEntity();
      $form['user_expire'] = [
        '#type' => 'details',
        '#title' => $this->t('User expiration'),
        '#open' => TRUE,
        '#weight' => 5,
      ];
      $form['user_expire']['user_expiration'] = [
        '#title' => $this->t('Set expiration for this user'),
        '#type' => 'checkbox',
        '#default_value' => !empty($entity->expiration),
      ];
      $form['user_expire']['container'] = [
        '#type' => 'container',
        '#states' => [
          'invisible' => [
            ':input[name="user_expiration"]' => [
              'checked' => FALSE,
            ],
          ],
        ],
      ];
      $form['user_expire']['container']['user_expiration_date'] = [
        '#title' => $this->t('Expiration date'),
        '#type' => 'datetime',
        '#description' => $this->t('The date on which this account will be disabled.'),
        '#date_date_format' => 'Y-m-d',
        '#date_time_element' => 'none',
        '#default_value' => $entity->expiration ? DrupalDateTime::createFromTimestamp($entity->expiration) : NULL,
        '#required' => !empty($form_state->getValue('user_expiration')),
      ];
    }
    $form['actions']['submit']['#submit'][] = 'user_expire_user_profile_form_submit';
  }

  /**
   * Implements hook_user_insert().
   */
  #[Hook('user_insert')]
  public function userInsert(EntityInterface $entity): void {
    _user_expire_save($entity);
  }

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // Start with per-role warnings (if enabled).
    $config = $this->configFactory->get('user_expire.settings');
    $send_expiration_warnings = $config->get('send_expiration_warnings') ?? TRUE;
    if ($send_expiration_warnings) {
      user_expire_expire_by_role_warning();
    }
    // Then do per-user blocking.
    user_expire_process_per_user_expiration();
    // Then per-role inactivity blocking.
    user_expire_expire_by_role();
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail($key, &$message, $params): void {
    if ($key == 'expiration_warning') {
      $langcode = $message['langcode'];
      $variables = [
        'user' => $params['account'],
      ];
      $language = $this->languageManager->getLanguage($langcode);
      $original_language = $this->languageManager->getConfigOverrideLanguage();
      $this->languageManager->setConfigOverrideLanguage($language);
      $config = $this->configFactory->get('user_expire.settings');
      $token_options = [
        'langcode' => $langcode,
        'callback' => 'user_mail_tokens',
        'clear' => TRUE,
      ];
      $message['subject'] .= PlainTextOutput::renderFromHtml($this->token->replace($config->get('expiration_warning_mail.subject'), $variables, $token_options));
      $message['body'][] = $this->token->replace($config->get('expiration_warning_mail.body'), $variables, $token_options);
      $this->languageManager->setConfigOverrideLanguage($original_language);
    }
  }

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo() {
    $info = [];
    $info['tokens']['user']['user_expire_date'] = [
      'name' => $this->t('User expiration date'),
      'description' => $this->t('The date when the user account will expire/be blocked.'),
    ];
    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
    $replacements = [];
    if ($type == 'user' && !empty($data['user'])) {
      $user = $data['user'];
      $config = $this->configFactory->get('user_expire.settings');
      foreach ($tokens as $name => $original) {
        switch ($name) {
          case 'user_expire_date':
            $expiration_timestamp = NULL;
            // First, check if user has a specific expiration date set.
            if (!empty($user->expiration)) {
              $expiration_timestamp = $user->expiration;
            }
            else {
              // Calculate role-based expiration.
              $expiration_timestamp = user_expire_calculate_role_expiration($user);
            }
            if ($expiration_timestamp) {
              // Get the configured date format, default to 'F j, Y'.
              $date_format = $config->get('expiration_date_format') ?: 'F j, Y';
              $replacements[$original] = $this->dateFormatter->format($expiration_timestamp, 'custom', $date_format);
            }
            else {
              // Return empty string if no expiration date is set.
              // Prevents confusing "No expiration date set" in emails.
              $replacements[$original] = '';
            }
            break;
        }
      }
    }
    return $replacements;
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for user entities.
   *
   * If the account was blocked but is now active, update the expiry so it is
   * not re-blocked by the next cron run.
   */
  #[Hook('user_presave')]
  public function userPresave(User $account): void {
    $original = DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0', fn() => $account->getOriginal(), fn() => $account->original);
    if (!empty($original) && $original->isBlocked() && $account->isActive()) {
      $account->setLastAccessTime($this->time->getRequestTime());
    }
  }

}
