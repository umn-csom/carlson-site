<?php

namespace Drupal\carlson_purge_trace\Form;

use Drupal\carlson_purge_trace\Controller\PurgeTraceProbeController;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a CSRF-protected purge trace validation probe form.
 */
class PurgeTraceProbeForm extends FormBase {

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected ClassResolverInterface $classResolver;

  /**
   * Constructs the form.
   */
  public function __construct(ClassResolverInterface $class_resolver) {
    $this->classResolver = $class_resolver;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('class_resolver'));
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carlson_purge_trace_probe';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#token'] = $this->getFormId();

    $output = $form_state->get('probe_output');
    if (is_array($output)) {
      $form['output'] = $output;
    }
    else {
      $form['description'] = [
        '#markup' => '<p>' . $this->t(
          'This probe temporarily saves and restores a content item to verify '
          . 'purge trace capture. Submit the form to run it.',
        ) . '</p>',
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run validation probe'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\carlson_purge_trace\Controller\PurgeTraceProbeController $probe */
    $probe = $this->classResolver->getInstanceFromDefinition(
      PurgeTraceProbeController::class,
    );
    $form_state->set('probe_output', $probe->run());
    $form_state->setRebuild(TRUE);
  }

}
