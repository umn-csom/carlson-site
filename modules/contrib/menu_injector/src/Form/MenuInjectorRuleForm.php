<?php

namespace Drupal\menu_injector\Form;

use Drupal\Core\Condition\ConditionManager;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityManager;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuParentFormSelector;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\Plugin\Context\ContextRepositoryInterface;
use Drupal\Core\ProxyClass\Routing\RouteBuilder;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\menu_injector\Entity\MenuInjectorRule;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MenuInjectorRuleForm extends EntityForm {

  /**
   * The menu link manager.
   *
   * @var \Drupal\Core\Menu\MenuLinkManagerInterface.
   */
  protected $menu_link_manager;

  /**
   * @param \Drupal\Core\Entity\Query\QueryFactory $entity_query
   *   The entity query.
   */
  public function __construct(
    QueryFactory $entity_query,
    EntityManager $entity_manager,
    MenuParentFormSelector $menu_parent_form_selector,
    MenuLinkManagerInterface $menu_link_manager,
    ConditionManager $condition_plugin_manager,
    ContextRepositoryInterface $context_repository,
    RouteBuilder $route_builder) {

    $this->entity_query = $entity_query;
    $this->entity_manager = $entity_manager;
    $this->menu_parent_form_selector = $menu_parent_form_selector;
    $this->menu_link_manager = $menu_link_manager;
    $this->condition_plugin_manager = $condition_plugin_manager;
    $this->context_repository = $context_repository;
    $this->route_builder = $route_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.query'),
      $container->get('entity.manager'),
      $container->get('menu.parent_form_selector'),
      $container->get('plugin.manager.menu.link'),
      $container->get('plugin.manager.condition'),
      $container->get('context.repository'),
      $container->get('router.builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // Allow parent to construct base form, set tree value.
    $form = parent::form($form, $form_state);
    $form['#tree'] = true;

    // Set these for use when attaching condition forms.
    $form_state->setTemporaryValue('gathered_contexts', $this->context_repository->getAvailableContexts());

    // Get all vocabularies.
    $vocabs = array();
    $vocabs_types = Vocabulary::loadMultiple();

    if (!empty($vocabs_types)) {
      foreach ($vocabs_types as $vocab_name => $vocab) {
        $vocabs[$vocab_name] = $vocab->label();
      }
      asort($vocabs);
    }

    // Get the menu injector rule entity.
    $rule = $this->entity;
    //$form_state->setCached(false);

    // Menu injector label.
    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $rule->getLabel(),
      '#description' => $this->t("Label for the Menu Injector rule."),
      '#required' => true,
    ];

    // Menu injector machine name.
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $rule->getId(),
      '#machine_name' => [
        'exists' => [$this, 'exist'],
      ],
      '#disabled' => !$rule->isNew(),
    ];

    // Menu injector vocabulary list.
    $form['vocab_list'] = array(
      '#type' => 'select',
      '#required' => true,
      '#options' => $vocabs,
      '#default_value' => $rule->getVocabList(),
      '#title' => $this->t('Vocabulary List'),
      '#description' => $this->t('Select the vocabulary list.')
    );

    // Vocab list wrapper
    $form['wrapper'] = array(
      '#type' => 'container',
      '#attributes' => array('id' => 'data-wrapper'),
    );

    // Menu injector taxonomy term
    $vocab_list_value = $rule->getVocabList();
    $taxonomy_options = [];

    $terms = $this->entity_manager->getStorage('taxonomy_term')->loadTree($vocab_list_value);
    if( !empty($terms) ) {
      foreach ($terms as $term) {
        $taxonomy_options[$term->tid] = $term->name;
      }
    }

    $form['wrapper']['refresh_taxonomy_terms_btn'] = array(
      '#type' => 'button',
      '#value' => 'Refresh Taxonomy Terms',
      '#ajax' => array(
          'callback' => '::changeTaxonomyTerms',
          'wrapper' => 'data-wrapper',
          'method' => 'replace',
      ),
    );

    $showHide = ( !empty($taxonomy_options) ) ? 'display: block;' : 'display: none;';
    $form['wrapper']['taxonomy_term'] = array(
      '#type' => 'select',
      '#required' => false,
      '#options' => $taxonomy_options,
      '#default_value' => $rule->getTaxonomyTerm(),
      '#title' => $this->t('Taxonomy Term'),
      '#description' => $this->t('Select the taxonomy term.'),
      '#attributes' => array(
          'id' => 'taxonomy-term-select',
      ),
    );

    return $form;
  }

  /**
   * Ajax handler for changing the taxonomy terms based on the vocabulary list.
   */
  public function changeTaxonomyTerms(array &$form, FormStateInterface $form_state) {
    $rule = $this->entity;
    $vocab_list_value = $rule->getVocabList();
    $taxonomy_options = [];

    $terms = $this->entity_manager->getStorage('taxonomy_term')->loadTree($vocab_list_value);
    if( !empty($terms) ) {
      foreach ($terms as $term) {
        $taxonomy_options[$term->tid] = $term->name;
      }
    }
    
    $showHide = ( !empty($taxonomy_options) ) ? 'display: block;' : 'display: none;';
    $trigger = $form_state->getTriggeringElement();

    if ($trigger['#value'] == 'Refresh Taxonomy Terms') {
      $form['wrapper']['taxonomy_term']['#options'] = $taxonomy_options;
    }
    return $form['wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $rule = $this->entity;
    $taxonomy_term = $form['wrapper']['taxonomy_term']['#value'];
    $form_state->setValue('taxonomy_term', $taxonomy_term);
    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {

    $rule = $this->entity;
    $is_new = $rule->isNew();

    // Save the menu injector rule and get the status for messaging.
    $status = $rule->save();
    
    if ($status && $is_new) {
      drupal_set_message($this->t('Rule %label has been added.', ['%label' => $rule->getLabel()]));
    }
    else if ($status) {
      drupal_set_message($this->t('Rule %label has been updated.', ['%label' => $rule->getLabel()]));
    }
    else {
      drupal_set_message($this->t('Rule %label was not saved.', ['%label' => $rule->getLabel()]), 'warning');
    }

    // Flush appropriate menu cache.
    $this->route_builder->rebuild();

    // Redirect back to the menu injector rule order form.
    $form_state->setRedirect('entity.menu_injector_rule.order_form');
  }

  /**
   * Returns boolean indicating whether or not this entity exists.
   *
   * @param  string $id The id of the entity.
   * @return bool       Whether or not the entity exists already.
   */
  public function exist($id) {
    $entity = $this->entity_query->get('menu_injector_rule')
      ->condition('id', $id)
      ->execute();
    return (bool) $entity;
  }
}
