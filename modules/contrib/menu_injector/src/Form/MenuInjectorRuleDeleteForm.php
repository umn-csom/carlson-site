<?php

namespace Drupal\menu_injector\Form;

use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the form to delete a Example.
 */

class MenuInjectorRuleDeleteForm extends EntityConfirmFormBase {

  /**
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(
    MenuLinkManagerInterface $menu_link_manager,
    RouteBuilder $route_builder) {

    $this->menu_link_manager = $menu_link_manager;
    $this->route_builder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.menu.link'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the %name rule?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.menu_injector_rule.order_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->entity->delete();
    \Drupal::messenger()->addStatus($this->t('The %label rule has been deleted.', ['%label' => $this->entity->getLabel()]));

    // Flush appropriate menu cache.
    $this->route_builder->rebuild();

    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
