<?php

namespace Drupal\carlson_recaptcha\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ModuleExtensionList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plain-text HTTP probe to confirm git deploy reached this environment.
 */
class DeploymentProbeController extends ControllerBase {

  /**
   * Module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * Drupal root directory (absolute path).
   *
   * @var string
   */
  protected string $appRoot;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Extension\ModuleExtensionList $module_extension_list
   *   Module extension list.
   * @param string $app_root
   *   Drupal root directory (absolute path).
   */
  public function __construct(
    ModuleExtensionList $module_extension_list,
    string $app_root,
  ) {
    $this->moduleExtensionList = $module_extension_list;
    $this->appRoot = $app_root;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.list.module'),
      (string) $container->getParameter('app.root'),
    );
  }

  /**
   * Returns deployment probe output as text/plain.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response with marker and mtimes for comparison to git.
   */
  public function probe(): Response {
    $relative = $this->moduleExtensionList->getPath('carlson_recaptcha');
    $probe_path = $this->appRoot . '/' . $relative . '/DEPLOYMENT_PROBE.txt';
    $marker = 'missing';
    $probe_mtime = 'n/a';
    if (is_readable($probe_path)) {
      $raw = file_get_contents($probe_path);
      $marker = $raw === FALSE ? 'unreadable' : trim((string) $raw);
      $mtime = @filemtime($probe_path);
      $probe_mtime = $mtime !== FALSE ? (string) $mtime : 'n/a';
    }

    $controller_path = __DIR__ . '/DeploymentProbeController.php';
    $ctrl_mtime = is_readable($controller_path)
      ? (string) @filemtime($controller_path)
      : 'n/a';

    $lines = [
      'carlson_recaptcha deployment probe',
      'marker: ' . $marker,
      'DEPLOYMENT_PROBE.txt mtime: ' . $probe_mtime,
      'DeploymentProbeController.php mtime: ' . $ctrl_mtime,
      '',
      'Change DEPLOYMENT_PROBE.txt in git, deploy, reload this URL to',
      'confirm the new marker appears.',
    ];

    return new Response(
      implode("\n", $lines),
      200,
      ['Content-Type' => 'text/plain; charset=UTF-8'],
    );
  }

}
