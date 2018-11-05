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

    $form['wrapper'] = array(
      '#type' => 'container',
      '#attributes' => array('id' => 'data-wrapper'),
    );

    $form['wrapper']['data_media'] = array(
      '#type' => 'select',
      '#required' => false,
      '#title' => $this->t('Options'),
      '#options' => array('aaa','bbb','cccc'),
      '#attributes' => array('id' => 'data-media-select'),
    );
    
    $form['wrapper']['more_data'] = array(
      '#type' => 'button',
      '#required' => true,
      '#value' => 'Show more',
      '#ajax' => array(
        // The callback to invoke to handle the server side of the Ajax event.
        //'callback' => [$form_state->getBuildInfo()['callback_object'], 'ajaxLoadMore'],
        // or: //'callback' => [$this, 'ajaxLoadMore'],
        'callback' => '::ajaxLoadMore',
        // @see: http://api.jquery.com/category/manipulation/
        'method' => 'replace', // May be: 'replaceWith' (default), 'append', 'prepend', 'before', 'after', or 'html'.
        // The HTML 'id' attribute of the area where the content returned by the callback should be placed.
        'wrapper' => 'data-wrapper',
      ),
    );

  //   // Vocab list wrapper
  //   $form['vocab_list_wrapper'] = array(
  //     '#type' => 'container',
  //     '#attributes' => array('id' => 'vocab-list-wrapper'),
  //   );

  //   // Menu injector vocabulary list.
  //   $form['vocab_list_wrapper']['vocab_list'] = array(
  //     '#type' => 'select',
  //     '#required' => TRUE,
  //     '#options' => $vocabs,
  //     '#default_value' => $rule->getVocabList(),
  //     '#title' => $this->t('Vocabulary List'),
  //     '#description' => $this->t('Select the vocabulary list.')
  //   );

  //   $form['vocab_list_wrapper']['refresh_taxonomy_terms_btn'] = array(
  //     '#type' => 'button',
  //     '#value' => 'Refresh',
  //     '#ajax' => array(
  //         'callback' => '::changeTaxonomyTerms',
  //         'wrapper' => 'refresh-terms-btn-wrapper',
  //     ),
  // );

  //   // Menu injector taxonomy term
  //   $vocab_list_value = $rule->getVocabList();
  //   $taxonomy_options = [];

  //   $terms = $this->entity_manager->getStorage('taxonomy_term')->loadTree($vocab_list_value);
  //   if( !empty($terms) ) {
  //     foreach ($terms as $term) {
  //       $taxonomy_options[$term->tid] = $term->name;
  //     }
  //   }

  //   $form['taxonomy_term_wrapper'] = array(
  //     '#type' => 'container',
  //     '#attributes' => array('id' => 'taxonomy-term-wrapper'),
  //   );

    return $form;
  }

  /**
   * Ajax handler for loading more radio options.
   */
  public function ajaxLoadMore(array &$form, FormStateInterface $form_state){
    // Add more options for radios.
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#value'] == 'Show more') {
      $form['wrapper']['data_media']['#title'] = $this->t('Options');
      $form['wrapper']['data_media']['#options'] = array('123','1234','4356','34WD');
    }
    return $form['wrapper'];
  }

  /**
   * The callback function for when the `change_taxonomy_terms` element is changed.
   *
   * What this returns will be replace the wrapper provided.
   */
  // public function changeTaxonomyTerms(array $form, FormStateInterface $form_state) {

  //   $rule = $this->entity;
  //   $vocab_list_value = $rule->getVocabList();
  //   $taxonomy_options = [];

  //   $terms = $this->entity_manager->getStorage('taxonomy_term')->loadTree($vocab_list_value);
  //   if( !empty($terms) ) {
  //     foreach ($terms as $term) {
  //       $taxonomy_options[$term->tid] = $term->name;
  //     }
  //   }
    
  //   $form['taxonomy_term']['#options'] = $taxonomy_options;
  //   return $form['taxonomy_term_wrapper'];
  // }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
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
