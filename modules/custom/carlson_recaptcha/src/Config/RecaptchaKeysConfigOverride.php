<?php

namespace Drupal\carlson_recaptcha\Config;

use Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Injects site/secret keys from private JSON into recaptcha contrib config.
 */
class RecaptchaKeysConfigOverride implements ConfigFactoryOverrideInterface {

  /**
   * Contrib config names keyed by JSON version keys (v2, v3).
   */
  protected const CONFIG_BY_VERSION = [
    'recaptcha.settings' => 'v2',
    'recaptcha_v3.settings' => 'v3',
  ];

  /**
   * Private key file reader.
   *
   * @var \Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile
   */
  protected RecaptchaPrivateKeyFile $keyFile;

  /**
   * Constructs the overrider.
   *
   * @param \Drupal\carlson_recaptcha\Service\RecaptchaPrivateKeyFile $key_file
   *   Private key file reader.
   */
  public function __construct(RecaptchaPrivateKeyFile $key_file) {
    $this->keyFile = $key_file;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $names = (array) $names;
    $relevant = array_intersect(
      array_keys(self::CONFIG_BY_VERSION),
      $names,
    );
    if ($relevant === []) {
      return [];
    }

    $keys = $this->keyFile->read();
    if ($keys === []) {
      return [];
    }

    $overrides = [];
    foreach ($relevant as $config_name) {
      $version = self::CONFIG_BY_VERSION[$config_name];
      if (
        empty($keys[$version]['site_key']) ||
        empty($keys[$version]['secret_key'])
      ) {
        continue;
      }
      $overrides[$config_name] = [
        'site_key' => $keys[$version]['site_key'],
        'secret_key' => $keys[$version]['secret_key'],
      ];
    }

    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'carlson_recaptcha_file_keys';
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject(
    $name,
    $collection = StorageInterface::DEFAULT_COLLECTION,
  ) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

}
