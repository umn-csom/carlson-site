<?php

namespace Drupal\carlson_general\Plugin\ImageEffect;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\image\Attribute\ImageEffect;
use Drupal\image\ConfigurableImageEffectBase;

/**
 * Provides a 'Box Blur' image effect with configurable strength.
 */
#[ImageEffect(
  id: "box_blur",
  label: new TranslatableMarkup("Box Blur"),
  description: new TranslatableMarkup("Applies a simple box blur to the image with configurable strength."),
)]
class BoxBlurEffect extends ConfigurableImageEffectBase {

  /**
   * {@inheritdoc}
   */
  public function applyEffect(ImageInterface $image) {
    /** @var \Drupal\Core\ImageToolkit\ToolkitInterface $toolkit */
    $toolkit = $image->getToolkit();
    // Only proceed if this is the GD toolkit.
    if ($toolkit && $toolkit->getPluginId() === 'gd') {
      /** @var \GdImage $resource */
      $resource = $toolkit->getImage();
      if (
        $resource
        && function_exists('imagecolorat')
        && function_exists('imagecreatetruecolor')
        && function_exists('imagesetpixel')
        && function_exists('imagecolorallocate')
      ) {
        $w = $image->getWidth();
        $h = $image->getHeight();
        $smoothed = imagecreatetruecolor($w, $h);

        // Applies a 3x3 box (mean) blur by averaging the color of the
        // surrounding pixels to create a smoothing effect. This is particularly
        // useful to avoid jagged edges when tiny thumbnails with harsh contrast
        // between pixels are upscaled to a larger size in the browser to be
        // used as a low-quality placeholder image while a high-quality larger
        // image is loaded in the background.
        for ($y = 0; $y < $h; $y++) {
          for ($x = 0; $x < $w; $x++) {
            // Initialize accumulators for red ($r), green ($g), blue ($b),
            // and a counter ($count).
            $r = $g = $b = $count = 0;
            // For each pixel in the image, iterate over the 3x3 neighborhood
            // centered at the current pixel.
            for ($dy = -1; $dy <= 1; $dy++) {
              for ($dx = -1; $dx <= 1; $dx++) {
                $nx = $x + $dx;
                $ny = $y + $dy;
                // Check if the neighbor is within bounds.
                if ($nx >= 0 && $nx < $w && $ny >= 0 && $ny < $h) {
                  // Get the color of the neighbor pixel.
                  $rgb = imagecolorat($resource, $nx, $ny);
                  // Extract red, green, and blue components from the neighbor's color and add them to the accumulators.
                  $r += ($rgb >> 16) & 0xFF;
                  $g += ($rgb >> 8) & 0xFF;
                  $b += $rgb & 0xFF;
                  $count++;
                }
              }
            }
            // Calculate the average color of the neighborhood by dividing by
            // the count (usually 9, but fewer at the edges).
            $r = round($r / $count);
            $g = round($g / $count);
            $b = round($b / $count);
            // Allocate the average color to the smoothed image.
            $color = imagecolorallocate($smoothed, $r, $g, $b);
            imagesetpixel($smoothed, $x, $y, $color);
          }
        }
        // Replace the original image with the smoothed image.
        $toolkit->setImage($smoothed);
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'strength' => 10,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['strength'] = [
      '#type' => 'number',
      '#title' => t('Smoothness level'),
      '#default_value' => $this->configuration['strength'] ?? 10,
      '#min' => 1,
      '#max' => 2048,
      '#description' => t('The GD <a href="https://www.php.net/manual/en/function.imagefilter.php#refsect1-function.imagefilter-examples" target="_blank">IMG_FILTER_SMOOTH</a> filter applies a 9-cell convolution matrix where center pixel has the weight arg1 and others weight of 1.0. The result is normalized by dividing the sum with arg1 + 8.0 (sum of the matrix). Any float is accepted, large value (in practice: 2048 or more) = no change.'),
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['strength'] = $form_state->getValue('strength');
  }
}
