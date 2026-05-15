<?php

namespace Drupal\carlson_general\Form;

use Drupal\carlson_recaptcha\Form\RecaptchaPrivateKeysForm as RecaptchaPrivateKeysFormBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Legacy route compatibility; same UX as carlson_recaptcha private keys form.
 */
class RecaptchaPrivateKeysForm extends RecaptchaPrivateKeysFormBase {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('carlson_general.recaptcha_private_key_file'),
    );
  }

}
