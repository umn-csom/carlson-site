# Edit Media Entity in Modal

The tiny module provides the ability to edit a Media entity in a modal window.
Also added edit this media button to EditorMediaDialog form that is used in the CKEditor embed media plugin.

## Installation
Install as usual.
```shell
composer require drupal/edit_media_modal
drush en edit_media_modal
```

## Usage
As default module provides an edit media link for the EditorMediaDialog. But You will be able to use this improvement in the many places where do you need that.

```php
  $form['edit_media_link'] = $media->toLink($this->t('Edit this media'), 'edit-form', [
    'query' => [
      'edit_media_in_modal' => TRUE, // Add that to have ajax actions for the media edit form.
      'destination' => \Drupal\Core\Url::fromRoute('<front>')->toString(), // Add that if you need redirect somewhere after submitting.
    ],
  ])->toRenderable();

  $form['edit_media_link']['#attributes'] = [
    'class' => [
      'js-media-library-edit-link',
      'media-library-edit__link',
      'use-ajax',
      'button',
    ],
    'target' => '_self',
    'data-dialog-options' => json_encode([
      'height' => '75%',
      'width' => '75%',
      'classes' => [
        'ui-dialog-content' => 'media-library-edit__modal',
      ],
    ]),
    'data-dialog-type' => 'modal',
  ];
```

## CKEditor 5 intergration

The module provides a CKEditor 5 plugin as CKEditor5 does not make use of the
`EditorMediaDialog`.

Once the module is installed a new "edit" button will be visible on the media
toolbar.


### Modifying the plugin

In the module directory, run `yarn install` to download the necessary assets.

The plugin can be built with `yarn build` or `yarn watch`.

Note: The `node_modules` directory must not be committed.


## LINKS
* Project page: https://www.drupal.org/project/edit_media_modal
