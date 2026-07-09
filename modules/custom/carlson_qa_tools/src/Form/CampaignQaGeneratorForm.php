<?php

namespace Drupal\carlson_qa_tools\Form;

use Drupal\block\Entity\Block;
use Drupal\block_content\Entity\BlockContent;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Generates campaign QA pages and block placements.
 */
class CampaignQaGeneratorForm extends FormBase {

  use StringTranslationTrait;

  /**
   * Constructs the QA generator form.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $siteConfigFactory,
    protected AliasManagerInterface $aliasManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('path_alias.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'carlson_qa_tools_campaign_generator';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['intro'] = [
      '#type' => 'container',
      'description' => [
        '#markup' => '<p>This generator creates stable QA nodes, reusable '
          . 'campaign block content, and placed block instances for campaign '
          . 'testing.</p><p>Generated pages:</p><ul><li>Sticky banner at the '
          . 'bottom</li><li>Sticky banner at the top</li><li>Modal only</li>'
          . '<li>Modal + top sticky</li><li>Modal + bottom sticky</li></ul>'
          . '<p>The current campaign architecture supports only one sticky '
          . 'banner per page, so a page with both top and bottom sticky '
          . 'banners is intentionally not generated.</p>',
      ],
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate campaign QA pages'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $pages = [
      [
        'title' => 'Campaign QA Sticky Bottom',
        'alias' => '/campaign-qa-sticky-bottom',
        'body' => 'QA page for a bottom-position sticky campaign.',
        'modal' => FALSE,
        'sticky' => [
          'position' => 'bottom',
          'color' => 'maroon',
          'info' => 'Campaign QA Sticky Bottom',
          'session_key' => 'campaign_qa_sticky_bottom',
        ],
      ],
      [
        'title' => 'Campaign QA Sticky Top',
        'alias' => '/campaign-qa-sticky-top',
        'body' => 'QA page for a top-position sticky campaign.',
        'modal' => FALSE,
        'sticky' => [
          'position' => 'top',
          'color' => 'ivory',
          'info' => 'Campaign QA Sticky Top',
          'session_key' => 'campaign_qa_sticky_top',
        ],
      ],
      [
        'title' => 'Campaign QA Modal',
        'alias' => '/campaign-qa-modal',
        'body' => 'QA page for a modal campaign.',
        'modal' => TRUE,
        'sticky' => FALSE,
      ],
      [
        'title' => 'Campaign QA Modal Plus Sticky Top',
        'alias' => '/campaign-qa-modal-sticky-top',
        'body' => 'QA page for a modal and top sticky campaign.',
        'modal' => TRUE,
        'sticky' => [
          'position' => 'top',
          'color' => 'ivory',
          'info' => 'Campaign QA Modal Sticky Top',
          'session_key' => 'campaign_qa_modal_sticky_top',
        ],
      ],
      [
        'title' => 'Campaign QA Modal Plus Sticky Bottom',
        'alias' => '/campaign-qa-modal-sticky-bottom',
        'body' => 'QA page for a modal and bottom sticky campaign.',
        'modal' => TRUE,
        'sticky' => [
          'position' => 'bottom',
          'color' => 'maroon',
          'info' => 'Campaign QA Modal Sticky Bottom',
          'session_key' => 'campaign_qa_modal_sticky_bottom',
        ],
      ],
    ];

    $links = [];
    foreach ($pages as $index => $definition) {
      $node = $this->ensurePageNode(
        $definition['title'],
        $definition['alias'],
        $definition['body'],
      );

      if (!empty($definition['modal'])) {
        $modal = $this->ensureModalCampaign(
          $definition['title'] . ' Modal',
          $definition['title'],
        );
        $this->ensureBlockPlacement(
          'carlson_qa_modal_' . $index,
          $modal,
          $definition['alias'],
          -10,
          'content',
        );
      }

      if (!empty($definition['sticky'])) {
        $sticky = $this->ensureStickyCampaign(
          $definition['sticky']['info'],
          $definition['title'],
          $definition['sticky']['position'],
          $definition['sticky']['color'],
          $definition['sticky']['session_key'],
        );
        $this->ensureBlockPlacement(
          'carlson_qa_sticky_' . $index,
          $sticky,
          $definition['alias'],
          -10,
          'content',
        );
      }

      $links[] = Link::fromTextAndUrl(
        $definition['alias'],
        Url::fromUri('internal:' . $definition['alias']),
      )->toString();
    }

    $this->messenger()->addStatus($this->t(
      'Generated campaign QA pages: @pages',
      ['@pages' => implode(', ', $links)],
    ));
  }

  /**
   * Creates or updates a QA page node.
   */
  protected function ensurePageNode(
    string $title,
    string $alias,
    string $body,
  ) {
    $storage = $this->entityTypeManager->getStorage('node');
    $existing_path = $this->aliasManager->getPathByAlias($alias);
    $node = NULL;

    if (preg_match('/^\\/node\\/(\\d+)$/', $existing_path, $matches)) {
      $node = $storage->load((int) $matches[1]);
    }

    if (!$node) {
      $ids = $this->entityTypeManager->getStorage('node')->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', 'page')
        ->condition('title', $title)
        ->range(0, 1)
        ->execute();
      if ($ids) {
        $node = $storage->load(reset($ids));
      }
    }

    if (!$node) {
      $node = $storage->create([
        'type' => 'page',
      ]);
    }

    $node->setTitle($title);
    $node->set('body', [
      'value' => '<p>' . $body . '</p>',
      'format' => 'basic_html',
    ]);
    $node->set('path', [
      'alias' => $alias,
      'pathauto' => 0,
    ]);

    if ($node->hasField('moderation_state')) {
      $node->set('moderation_state', 'published');
    }
    if (method_exists($node, 'setPublished')) {
      $node->setPublished(TRUE);
    }

    $node->save();
    return $node;
  }

  /**
   * Creates or updates a reusable modal campaign.
   */
  protected function ensureModalCampaign(string $info, string $title): BlockContent {
    $storage = $this->entityTypeManager->getStorage('block_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'modal_campaign')
      ->condition('info', $info)
      ->range(0, 1)
      ->execute();
    $campaign = $ids
      ? $storage->load(reset($ids))
      : $storage->create(['type' => 'modal_campaign']);

    $campaign->set('info', $info);
    $campaign->set('field_modal_eyebrow', 'QA');
    $campaign->set('field_modal_title', $title);
    $campaign->set('field_campaign_text', [
      'value' => '<p>This is a generated modal QA campaign.</p>',
      'format' => 'basic_html',
    ]);
    $campaign->set('field_modal_cta_url', [
      'uri' => 'internal:/',
      'title' => 'Learn more',
    ]);
    $campaign->set('field_modal_decline_text', 'Maybe later');
    $campaign->set('field_campaign_delay_seconds', 0);
    $campaign->set('field_campaign_dismiss_days', 0);
    $campaign->set('field_campaign_session_key', $this->normalizeKey($info));

    if ($campaign->hasField('field_campaign_stop_on_convert')) {
      $campaign->set('field_campaign_stop_on_convert', 0);
    }
    if (method_exists($campaign, 'setPublished')) {
      $campaign->setPublished(TRUE);
    }

    $campaign->save();
    return $campaign;
  }

  /**
   * Creates or updates a reusable sticky campaign.
   */
  protected function ensureStickyCampaign(
    string $info,
    string $title,
    string $position,
    string $color,
    string $session_key,
  ): BlockContent {
    $storage = $this->entityTypeManager->getStorage('block_content');
    $ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'sticky_bar_campaign')
      ->condition('info', $info)
      ->range(0, 1)
      ->execute();
    $campaign = $ids
      ? $storage->load(reset($ids))
      : $storage->create(['type' => 'sticky_bar_campaign']);

    $campaign->set('info', $info);
    $campaign->set('field_campaign_text', [
      'value' => '<p>This is a generated sticky QA campaign for ' . $title
        . '.</p>',
      'format' => 'basic_html',
    ]);
    $campaign->set('field_campaign_position', $position);
    if ($campaign->hasField('field_campaign_color_scheme')) {
      $campaign->set('field_campaign_color_scheme', $color);
    }
    $campaign->set('field_campaign_delay_seconds', 0);
    $campaign->set('field_campaign_dismiss_days', 0);
    $campaign->set('field_campaign_session_key', $session_key);

    if (method_exists($campaign, 'setPublished')) {
      $campaign->setPublished(TRUE);
    }

    $campaign->save();
    return $campaign;
  }

  /**
   * Creates or updates a placed block instance for a QA campaign.
   */
  protected function ensureBlockPlacement(
    string $block_id,
    BlockContent $campaign,
    string $alias,
    int $weight,
    string $region,
  ): Block {
    $theme = $this->siteConfigFactory->get('system.theme')->get('default')
      ?: 'carlson_refresh';
    $plugin_id = 'block_content:' . $campaign->uuid();

    $block = Block::load($block_id) ?: Block::create([
      'id' => $block_id,
      'theme' => $theme,
      'region' => $region,
      'plugin' => $plugin_id,
    ]);

    $block->set('theme', $theme);
    $block->set('region', $region);
    $block->set('weight', $weight);
    $block->set('status', TRUE);
    $block->set('plugin', $plugin_id);
    $block->set('settings', [
      'id' => $plugin_id,
      'label' => $campaign->label(),
      'label_display' => FALSE,
      'provider' => 'block_content',
      'view_mode' => 'full',
    ]);
    $block->set('visibility', [
      'condition_group' => [
        'id' => 'condition_group',
        'negate' => FALSE,
        'context_mapping' => [],
        'block_visibility_group' => '',
      ],
      'request_path' => [
        'id' => 'request_path',
        'negate' => FALSE,
        'context_mapping' => [],
        'pages' => $alias,
      ],
    ]);

    $block->save();
    return $block;
  }

  /**
   * Normalizes a string into a simple analytics key.
   */
  protected function normalizeKey(string $value): string {
    $normalized = strtolower($value);
    $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? '';
    return trim($normalized, '_');
  }

}
