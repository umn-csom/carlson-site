<?php

namespace Drupal\menu_injector\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\Core\Url;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MenuInjectorOrderForm.
 *
 * @package Drupal\menu_injector\Form
 */
class MenuInjectorOrderForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface.
   */
  protected $menu_link_manager;


  /**
   * The route builder
   *
   */

  protected $route_builder;

  public function __construct(
    MenuLinkManagerInterface $menu_link_manager,
    EntityTypeManagerInterface $entity_type_manager,
    RouteBuilder $route_builder) {

    $this->menu_link_manager = $menu_link_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->route_builder = $route_builder;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.menu.link'),
      $container->get('entity_type.manager'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'menu_injector_order_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('menu_injector.menuinjectorororder_config');

    // Get all the rules.
    $query = $this->entityTypeManager->getStorage('menu_injector_rule')->getQuery();
    $results = $query->sort('label')->accessCheck(TRUE)->execute();
    $rules = $this->entityTypeManager->getStorage('menu_injector_rule')->loadMultiple($results);

    // Menu Injector rules order (tabledrag).
    $form['#tree'] = TRUE;
    $form['rules'] = [
      '#type' => 'table',
      '#empty' => $this->t('No rules have been created yet.'),
      '#title' => $this->t('Rules processing order'),
      '#header' => [
        $this->t('Rule'),
        $this->t('Enabled'),
        $this->t('Operations'),
      ],
    ];

    // Display table of rules.
    foreach ($rules as $rule) {
      $form['rules'][$rule->getId()] = [
        '#attributes' => ['class' => ['draggable']],
        'title' => [
          '#markup' => '<strong>' . $rule->getLabel() . '</strong>',
        ],
        'enabled' => [
          '#type' => 'checkbox',
          '#default_value' => $rule->getEnabled(),
        ],
        'operations' => [
          '#type' => 'dropbutton',
          '#links' => [
            'edit' => [
              'title' => $this->t('Edit'),
              'url' => Url::fromRoute('entity.menu_injector_rule.edit_form', ['menu_injector_rule' => $rule->getId()]),
            ],
            'delete' => [
              'title' => $this->t('Delete'),
              'url' => Url::fromRoute('entity.menu_injector_rule.delete_form', ['menu_injector_rule' => $rule->getId()]),
            ],
          ],
        ],
      ];
    }

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
      '#button_type' => 'primary',
    ];

    // By default, render the form using theme_system_config_form().
    $form['#theme'] = 'system_config_form';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $storage = $this->entityTypeManager->getStorage('menu_injector_rule');
    $values = $form_state->getValue('rules');
    $rules = $storage->loadMultiple(array_keys($values));

    foreach ($rules as $rule) {
      $value = $values[$rule->getId()];
      $rule->setEnabled((bool) $value['enabled']);
      $storage->save($rule);
    }

    // Flush appropriate menu cache.
    $this->route_builder->rebuild();

    \Drupal::messenger()->addStatus($this->t('The new rules ordering has been applied.'));
  }
}
