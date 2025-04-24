<?php

namespace Drupal\Tests\edit_media_modal\FunctionalJavascript;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\editor\Entity\Editor;
use Drupal\file\Entity\File;
use Drupal\filter\Entity\FilterFormat;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\media\Entity\Media;
use Drupal\Tests\ckeditor5\Traits\CKEditor5TestTrait;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\TestFileCreationTrait;

/**
 * @group edit_media_modal
 */
class EditMediaModalTest extends WebDriverTestBase {

  use CKEditor5TestTrait;
  use MediaTypeCreationTrait;
  use TestFileCreationTrait;

  /**
   * The user to use during testing.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * The sample Media entity to embed.
   *
   * @var \Drupal\media\MediaInterface
   */
  protected $media;

  /**
   * A host entity with a body field to embed media in.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected $host;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ckeditor5',
    'media',
    'node',
    'text',
    'media_test_embed',
    'media_library',
    'ckeditor5_test',
    'edit_media_modal',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'starterkit_theme';


  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    EntityViewMode::create([
      'id' => 'media.view_mode_1',
      'targetEntityType' => 'media',
      'status' => TRUE,
      'enabled' => TRUE,
      'label' => 'View Mode 1',
    ])->save();

    FilterFormat::create([
      'format' => 'test_format',
      'name' => 'Test format',
      'filters' => [
        'filter_html' => [
          'status' => TRUE,
          'settings' => [
            'allowed_html' => '<p> <br> <strong> <em> <a href> <drupal-media data-entity-type data-entity-uuid data-align data-view-mode data-caption alt>',
          ],
        ],
        'filter_align' => ['status' => TRUE],
        'filter_caption' => ['status' => TRUE],
        'media_embed' => [
          'status' => TRUE,
          'settings' => [
            'default_view_mode' => 'view_mode_1',
            'allowed_view_modes' => ['view_mode_1'],
            'allowed_media_types' => [],
          ],
        ],
      ],
    ])->save();

    Editor::create([
      'editor' => 'ckeditor5',
      'format' => 'test_format',
      'settings' => [
        'toolbar' => [
          'items' => [
            'sourceEditing',
            'link',
            'bold',
            'italic',
          ],
        ],
        'plugins' => [
          'ckeditor5_sourceEditing' => [
            'allowed_tags' => [],
          ],
          'media_media' => [
            'allow_view_mode_override' => TRUE,
          ],
        ],
      ],
      'image_upload' => [
        'status' => FALSE,
      ],
    ])->save();

    // Note that media_install() grants 'view media' to all users by default.
    $this->adminUser = $this->drupalCreateUser([
      'use text format test_format',
      'bypass node access',
      'administer media'
    ]);

    // Create a sample media entity to be embedded.
    $this->createMediaType('image', ['id' => 'image']);
    File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ])->save();
    $this->media = Media::create([
      'bundle' => 'image',
      'name' => 'Screaming hairy armadillo',
      'field_media_image' => [
        [
          'target_id' => 1,
          'alt' => 'default alt',
          'title' => 'default title',
        ],
      ],
    ]);
    $this->media->setOwner($this->adminUser);
    $this->media->save();

    // Set created media types for each view mode.
    EntityViewDisplay::create([
      'id' => 'media.image.view_mode_1',
      'targetEntityType' => 'media',
      'status' => TRUE,
      'bundle' => 'image',
      'mode' => 'view_mode_1',
    ])
      ->setComponent('name', ['type' => 'text_default'])
      ->save();

    EntityFormDisplay::load('media.image.default')
      ->setComponent('name', ['type' => 'string_textfield']);

    // Create a sample host entity to embed media in.
    $this->drupalCreateContentType(['type' => 'blog']);
    $this->host = $this->createNode([
      'type' => 'blog',
      'title' => 'Animals with strange names',
      'body' => [
        'value' => '<drupal-media data-entity-type="media" data-entity-uuid="' . $this->media->uuid() . '"></drupal-media>',
        'format' => 'test_format',
      ],
    ]);
    $this->host->save();

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test that media can be updated from CKeditor5
   */
  public function testUpdate() {
    $this->drupalGet($this->host->toUrl('edit-form'));
    $this->waitForEditor();
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Name is present in the previewed media.
    $this->assertNotEmpty($preview = $assert_session->waitForElementVisible('css', '.ck-widget.drupal-media > [data-drupal-media-preview="ready"] > .media', 30000));
    $this->assertStringContainsString('Screaming hairy armadillo', $preview->getHtml());

    // Test that clicking the media widget triggers a CKEditor balloon panel
    // with an "Edit media" button.
    $this->click('.ck-widget.drupal-media');
    $this->assertVisibleBalloon('[aria-label="Drupal Media toolbar"]');

    $this->getBalloonButton('Edit media')->click();

    // Update the media name.
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-modal #edit-media-form-modal'));
    $this->assertNotEmpty($name_field = $assert_session->waitForElementVisible('css', '#drupal-modal #edit-media-form-modal input[name="name[0][value]"]'));
    $name_field->setValue('Savory Crisp Airway');
    $assert_session->elementExists('css', '.ui-dialog-buttonpane')->pressButton('Save');
    $assert_session->assertWaitOnAjaxRequest();

    // Assert media has been updated.
    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    $media_storage->resetCache([$this->media->id()]);
    $this->media = Media::load($this->media->id());
    $this->assertEquals('Savory Crisp Airway', $this->media->label());

    // @todo - Verify the preview is updated.
    // When the modal is saved an attribute is updated on the media which
    // triggers the reload. This doesn't seem to be picking up the updated
    // media in the test - unsure if the assertWaitOnAjaxRequest picks up the
    // preview request.
    // $assert_session->assertWaitOnAjaxRequest();
    // $assert_session->waitForText('Savory Crisp Airway');
    // $this->assertNotEmpty($preview = $assert_session->waitForElementVisible('css', '.ck-widget.drupal-media > [data-drupal-media-preview="ready"] > .media', 30000));
    // $this->assertStringContainsString('Savory Crisp Airway', $preview->getHtml());
  }

}
