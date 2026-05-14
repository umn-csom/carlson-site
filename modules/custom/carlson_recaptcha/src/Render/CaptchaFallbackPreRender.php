<?php

namespace Drupal\carlson_recaptcha\Render;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Marks CAPTCHA render arrays after v3 falls back to the v2 checkbox.
 */
final class CaptchaFallbackPreRender implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'markV3V2Fallback',
      'applyFormChrome',
    ];
  }

  /**
   * Whether a form element is the CAPTCHA widget in v3→v2 checkbox fallback.
   *
   * @param array $element
   *   A render array, typically with #type captcha.
   *
   * @return bool
   *   TRUE when this element is that CAPTCHA challenge.
   */
  public static function captchaElementIsV3ToV2Fallback(array $element): bool {
    if (($element['#type'] ?? '') !== 'captcha') {
      return FALSE;
    }
    return ($element['#captcha_validate'] ?? '') === 'recaptcha_v3_validate'
      && ($element['#captcha_type_challenge'] ?? '') === 'reCAPTCHA';
  }

  /**
   * Whether this CAPTCHA render array is the v3→v2 checkbox fallback.
   *
   * @param array $element
   *   The CAPTCHA render array.
   *
   * @return bool
   *   TRUE when showing v2 after v3 was unable to verify.
   */
  private static function isV3ToV2Fallback(array $element): bool {
    return static::captchaElementIsV3ToV2Fallback($element);
  }

  /**
   * Whether the form subtree contains the v3→v2 CAPTCHA challenge.
   *
   * @param array $element
   *   A form or element subtree.
   *
   * @return bool
   *   TRUE when a matching CAPTCHA element exists.
   */
  private static function formSubtreeHasV3ToV2FallbackCaptcha(array $element): bool {
    foreach (Element::children($element) as $key) {
      if (
        isset($element[$key])
        && is_array($element[$key])
        && static::formSubtreeHasV3ToV2FallbackCaptcha($element[$key])
      ) {
        return TRUE;
      }
    }
    return static::captchaElementIsV3ToV2Fallback($element);
  }

  /**
   * Adds a form class and UX library when v3 has fallen back to the v2 widget.
   *
   * Runs at form root #after_build so nested CAPTCHA #process has finished.
   *
   * @param array $form
   *   The complete form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The form with optional class and attached library.
   */
  public static function applyFormChrome(
    array $form,
    FormStateInterface $form_state,
  ): array {
    if (!static::formSubtreeHasV3ToV2FallbackCaptcha($form)) {
      return $form;
    }
    if (empty($form['carlson_recaptcha_v2_fallback_notice'])) {
      $form['carlson_recaptcha_v2_fallback_notice'] =
        static::buildFallbackNotice();
    }
    static::weightFallbackCaptchaBeforeActions($form);
    $form['#attributes']['class'][] = 'carlson-recaptcha-v2-fallback';
    $form['#attached']['library'][] = 'carlson_recaptcha/v2_fallback_scroll';
    $form['#attached']['library'][] = 'carlson_recaptcha/v2_fallback_chrome';
    return $form;
  }

  /**
   * Top-of-form notice: warning icon plus instructions for the v2 checkbox.
   *
   * Warning glyph path derived from Bootstrap Icons (MIT License).
   *
   * @return array
   *   A render array for the notice container.
   */
  private static function buildFallbackNotice(): array {
    $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" width="1.125em" height="1.125em" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8.982 1.566a1.13 1.13 0 0 0-1.964 0L.165 13.233a1.131 1.131 0 0 0 .982 1.683h13.706a1.131 1.131 0 0 0 .982-1.683L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
SVG;
    return [
      '#type' => 'container',
      '#weight' => -1000,
      '#attributes' => [
        'class' => ['carlson-recaptcha-v2-fallback-notice'],
        'role' => 'status',
      ],
      'row' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['carlson-recaptcha-v2-fallback-notice__row'],
        ],
        'icon' => [
          '#type' => 'markup',
          '#markup' => Markup::create(
            '<span class="carlson-recaptcha-v2-fallback-notice__icon">'
            . trim($svg)
            . '</span>'
          ),
        ],
        'text' => [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#attributes' => [
            'class' => ['carlson-recaptcha-v2-fallback-notice__text'],
          ],
          '#value' => (string) \Drupal::translation()->translate(
            'To complete your submission, please use the checkbox below to '
            . 'confirm you are a human, and then submit the form.'
          ),
        ],
      ],
    ];
  }

  /**
   * Sets CAPTCHA weight so the widget renders before submit actions.
   *
   * @param array $form
   *   The form render array; modified by reference.
   */
  private static function weightFallbackCaptchaBeforeActions(array &$form): void {
    $location = static::findFallbackCaptchaLocation($form);
    if ($location === NULL) {
      return;
    }
    $captcha_key = $location['key'];
    $parent = &static::arrayParentRef($form, $location['path']);
    $actions_key = static::findActionsSiblingKey($parent);
    if ($actions_key !== NULL) {
      $action_weight = $parent[$actions_key]['#weight'] ?? 1000;
      $parent[$captcha_key]['#weight'] = $action_weight - 1;
    }
  }

  /**
   * Finds the first webform_actions or core actions container among siblings.
   *
   * @param array $parent
   *   A form subtree whose children are searched.
   *
   * @return string|null
   *   A child key or NULL.
   */
  private static function findActionsSiblingKey(array $parent): ?string {
    foreach (Element::children($parent) as $sk) {
      if (($parent[$sk]['#type'] ?? '') === 'webform_actions') {
        return $sk;
      }
    }
    foreach (Element::children($parent) as $sk) {
      if (($parent[$sk]['#type'] ?? '') === 'actions') {
        return $sk;
      }
    }
    return NULL;
  }

  /**
   * Locates the v3→v2 fallback CAPTCHA element in the form tree.
   *
   * @param array $element
   *   Form subtree to search.
   * @param array $path
   *   Keys from the form root to the parent of the current subtree.
   *
   * @return array|null
   *   Keys path (parent) and captcha key, or NULL.
   */
  private static function findFallbackCaptchaLocation(
    array $element,
    array $path = [],
  ): ?array {
    foreach (Element::children($element) as $key) {
      if (!isset($element[$key]) || !is_array($element[$key])) {
        continue;
      }
      if (static::captchaElementIsV3ToV2Fallback($element[$key])) {
        return ['path' => $path, 'key' => $key];
      }
      $found = static::findFallbackCaptchaLocation(
        $element[$key],
        array_merge($path, [$key]),
      );
      if ($found !== NULL) {
        return $found;
      }
    }
    return NULL;
  }

  /**
   * Returns a reference to the nested array at $path from $root.
   *
   * @param array $root
   *   The form array.
   * @param array $path
   *   Consecutive child keys; empty for the root.
   *
   * @return array
   *   Reference to the parent array that directly holds the captcha key.
   */
  private static function &arrayParentRef(array &$root, array $path): array {
    $ref = &$root;
    foreach ($path as $segment) {
      $ref = &$ref[$segment];
    }
    return $ref;
  }

  /**
   * Flags the render element so preprocess can hide the CAPTCHA legend.
   *
   * @param array $element
   *   The CAPTCHA render array.
   *
   * @return array
   *   The element, possibly with #carlson_recaptcha_v3_v2_fallback set.
   */
  public static function markV3V2Fallback(array $element): array {
    if (static::isV3ToV2Fallback($element)) {
      $element['#carlson_recaptcha_v3_v2_fallback'] = TRUE;
    }
    return $element;
  }

}
