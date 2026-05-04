<?php

namespace Drupal\carlson_recaptcha\Render;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Marks CAPTCHA render arrays after v3 falls back to the v2 checkbox.
 */
final class CaptchaFallbackPreRender implements TrustedCallbackInterface {

  /**
   * Weight used to float the v2 checkbox CAPTCHA ahead of typical form fields.
   */
  private const V2_FALLBACK_WEIGHT = -100;

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks(): array {
    return [
      'markV3V2Fallback',
      'promoteV2FallbackWeight',
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
    $form['#attributes']['class'][] = 'carlson-recaptcha-v2-fallback';
    $form['#attached']['library'][] = 'carlson_recaptcha/v2_fallback_chrome';
    return $form;
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

  /**
   * Moves the fallback CAPTCHA ahead of sibling fields on the same parent.
   *
   * Runs after #process so challenge type and validate callback are final.
   *
   * @param array $element
   *   The CAPTCHA form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The possibly reweighted element.
   */
  public static function promoteV2FallbackWeight(
    array $element,
    FormStateInterface $form_state,
  ): array {
    if (static::isV3ToV2Fallback($element)) {
      $element['#weight'] = static::V2_FALLBACK_WEIGHT;
    }
    return $element;
  }

}
