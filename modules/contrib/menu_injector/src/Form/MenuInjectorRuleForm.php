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
use Drupal\system\Entity\Menu;
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

    // Get all menus.
    $custom_menus = Menu::loadMultiple();
    foreach ($custom_menus as $menu_name => $menu) {
      $custom_menus[$menu_name] = $menu->label();
    }
    asort($custom_menus);
    $menu_options = $this->menu_parent_form_selector->getParentSelectOptions(null, $custom_menus);

    // Get the menu injector rule entity.
    $rule = $this->entity;
    $form_state->setCached(false);

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

    // Menu inject mode.
    $form['menu_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu Mode'),
      '#options' => array('active_trail' => 'Active Trail', 'ghost' => 'Ghost'),
      '#required' => true,
      '#default_value' => $rule->getMenuMode(),
      '#description' => $this->t('If you choose, "Active Trail" the injected menu will appear at the menu parent trail. If you choose "Ghost" then it will appear on any taxonomy matches.'),
    ];

    // Menu injector parent menu tree item.
    $form['parent'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu Parent'),
      '#options' => $menu_options,
      '#required' => false,
      '#default_value' => $rule->getParent(),
      '#description' => $this->t('Select the place in the menu where the rule should position its menu links and follow the active trail.')
    ];

    // Menu inject - menu links.
    $form['menu_choice'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu Choice'),
      '#options' => $custom_menus,
      '#required' => true,
      '#default_value' => $rule->getMenuChoice(),
      '#description' => $this->t('Select which custom menu to inject the links into.'),
    ];

    // Menu inject - menu links.
    $form['menu_links'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu Links'),
      '#options' => $menu_options,
      '#required' => true,
      '#default_value' => $rule->getMenuLinks(),
      '#description' => $this->t('Select the menu links to place in.'),
    ];

    // Get all content types.
    $content_types = \Drupal\node\Entity\NodeType::loadMultiple();
    $content_types_all = [];
    foreach ($content_types as $content_type) {
      $content_types_all[$content_type->id()] = $content_type->label();
    }

    // Menu content types selection.
    $form['content_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Content Type'),
      '#options' => $content_types_all,
      '#required' => true,
      '#default_value' => $rule->getContentType(),
      '#description' => $this->t('Select the content type to then select the mapping fields associated with it.'),
    ];

    // Content type list wrapper
    $form['ct_wrapper'] = array(
      '#type' => 'container',
      '#attributes' => array('id' => 'data-wrapper2'),
    );

    $form['ct_wrapper']['refresh_content_type_fields_btn'] = array(
      '#type' => 'button',
      '#required' => false,
      '#value' => 'Refresh Content Type Fields',
      '#ajax' => array(
          'callback' => '::changeContentTypeFields',
          'wrapper' => 'data-wrapper2',
          'method' => 'replace',
      ),
    );
    
    $content_fields_all = [];
    if( $rule->getContentType() ) {
      $content_fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $rule->getContentType());
      
      if( !empty($content_fields) ) {
        foreach ($content_fields as $field_name => $field_definition) {
          $label = $field_definition->getLabel();
          if( gettype($label) === 'string' ) {
            $content_fields_all[ $field_name ] = $field_definition->getLabel();
          }
        }
      }
    }

    // Menu inject mode.
    $form['ct_wrapper']['taxonomy_map_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Taxonomy Field Map'),
      '#options' => $content_fields_all,
      '#required' => false,
      '#default_value' => $rule->getTaxonomyMapField(),
      '#description' => $this->t('Select the taxonomy field to map.'),
    ];

    // Menu injector vocabulary list.
    $form['vocab_list'] = array(
      '#type' => 'select',
      '#required' => false,
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
      '#required' => false,
      '#value' => 'Refresh Taxonomy Terms',
      '#ajax' => array(
          'callback' => '::changeTaxonomyTerms',
          'wrapper' => 'data-wrapper',
          'method' => 'replace',
      ),
    );

    $default_terms = explode(',', $rule->getTaxonomyTerm());
    $form['wrapper']['taxonomy_term'] = array(
      '#type' => 'select',
      '#required' => false,
      '#multiple' => true,
      '#options' => $taxonomy_options,
      '#default_value' => $default_terms,
      '#title' => $this->t('Taxonomy Terms'),
      '#description' => $this->t('Select the taxonomy terms.'),
      '#attributes' => array(
          'id' => 'taxonomy-term-select',
          'style' => 'background: none; padding: 0; width: 300px; height: 100px;'
      ),
    );

    return $form;
  }

  /**
   * Ajax handler for changing the content fields based on the content type bundle.
   */
  public function changeContentTypeFields(array &$form, FormStateInterface $form_state) {
    $rule = $this->entity;
    if( $rule->getContentType() ) {
      $fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', $rule->getContentType());
      $content_fields_all = [];

      if( !empty($fields) ) {
        foreach ($fields as $field_name => $field_definition) {
          $label = $field_definition->getLabel();
          if( gettype($label) === 'string' ) {
            $content_fields_all[ $field_name ] = $field_definition->getLabel();
          }
        }

        if( !empty($content_fields_all) ) {
          $trigger = $form_state->getTriggeringElement();
          if ($trigger['#value'] == 'Refresh Content Type Fields') {
            $form['ct_wrapper']['taxonomy_map_field']['#options'] = $content_fields_all;
            $form['ct_wrapper']['taxonomy_map_field']['#default_value'] = $rule->getTaxonomyMapField();
          }
          return $form['ct_wrapper'];
        }
      }
    }
  }

  /**
   * Ajax handler for changing the taxonomy terms based on the vocabulary list.
   */
  public function changeTaxonomyTerms(array &$form, FormStateInterface $form_state) {
    $rule = $this->entity;
    $vocab_list_value = $rule->getVocabList();
    $default_terms = explode(',', $rule->getTaxonomyTerm());
    $taxonomy_options = [];

    $terms = $this->entity_manager->getStorage('taxonomy_term')->loadTree($vocab_list_value);
    if( !empty($terms) ) {
      foreach ($terms as $term) {
        $taxonomy_options[$term->tid] = $term->name;
      }
    }
    
    $trigger = $form_state->getTriggeringElement();
    if ($trigger['#value'] == 'Refresh Taxonomy Terms') {
      $form['wrapper']['taxonomy_term']['#options'] = $taxonomy_options;
      $form['wrapper']['taxonomy_term']['#default_value'] = $default_terms;
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
    $taxonomy_term = implode(',', $taxonomy_term);
    $form_state->setValue('taxonomy_term', $taxonomy_term);

    $taxonomy_map_field = $form['ct_wrapper']['taxonomy_map_field']['#value'];
    $form_state->setValue('taxonomy_map_field', $taxonomy_map_field);

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
