import { Plugin } from 'ckeditor5/src/core';
import { IconPencil } from 'ckeditor5/src/icons';
import { ButtonView } from 'ckeditor5/src/ui';

export default class EditMediaModal extends Plugin {

  constructor(editor) {
    super(editor);

    // Add our custom attribute to the media plugins converterAttributes.
    // This triggers rerendering when the attribute changes.
    const mediaEditing = editor.plugins.get('DrupalMediaEditing');
    mediaEditing.converterAttributes.push('emmDrupalMediaUpdated');

    // Cache for access check results by UUID
    this.accessCache = new Map();
  }

  init() {
    this._defineSchema();
    this._createButton();
  }

  _defineSchema() {
    this.editor.model.schema.extend('drupalMedia', {
      allowAttributes: ['emmDrupalMediaUpdated'],
    });
  }

  async _checkAccess(uuid) {
    // Check cache first
    if (this.accessCache.has(uuid)) {
      return this.accessCache.get(uuid);
    }

    try {
      const response = await fetch(
        Drupal.url(`edit-media-modal/check-access/${uuid}`)
      );

      // Only status 200 means access is granted
      const hasAccess = response.status === 200;

      // Cache the result
      this.accessCache.set(uuid, hasAccess);

      return hasAccess;
    } catch (error) {
      return false;
    }
  }

  async _updateButtonState(view, extraSettings) {
    const element = this.editor.model.document.selection.getSelectedElement();

    // Button is only relevant for drupalMedia elements
    if (element?.name === 'drupalMedia') {
      const uuid = element.getAttribute('drupalMediaEntityUuid');

      if (extraSettings.skipAccessCheck) {
        // Skip access check - always enable button
        view.isEnabled = true;
      } else {
        // Check access and enable/disable button accordingly
        const hasAccess = await this._checkAccess(uuid);
        view.isEnabled = hasAccess;
      }
    } else {
      // Not a media element - disable button
      view.isEnabled = false;
    }
  }

  _createButton() {
    const editor = this.editor;
    const options = editor.config.get('editMediaModal');
    const { openDialog, dialogSettings, editMediaModalForms, extraSettings = {} } = options;
    const format = editor.sourceElement.getAttribute('data-editor-active-text-format');
    const langCode = editor.locale.contentLanguage;
    const dialogModalSettings = {
      ...dialogSettings,
      height: dialogSettings.height + '%'
    };

    editor.ui.componentFactory.add('editMediaButton', (locale) => {
      const view = new ButtonView(locale);

      view.set({
        label: Drupal.t('Edit media'),
        icon: IconPencil,
        tooltip: true,
        isEnabled: false,
      });

      // Check initial state when button is created
      this._updateButtonState(view, extraSettings);

      // Listen to selection changes to enable/disable button
      this.listenTo(editor.model.document.selection, 'change', () => {
        this._updateButtonState(view, extraSettings);
      });

      // Also listen to when the button becomes visible in the toolbar
      // This ensures we check access when media balloon toolbar appears
      view.on('change:isVisible', () => {
        if (view.isVisible) {
          this._updateButtonState(view, extraSettings);
        }
      });

      this.listenTo(view, 'execute', () => {
        const element = editor.model.document.selection.getSelectedElement();
        const uuid = element.getAttribute('drupalMediaEntityUuid');

        fetch(Drupal.url(`edit-media-modal/edit-url/${uuid}?lang=${langCode}&text_format=${format}`))
        .then(response => {
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then((data) => {
          openDialog(
            data.url,
            () => {
              const element = editor.model.document.selection.getSelectedElement();
              if (element?.name === 'drupalMedia') {
                editor.model.change(writer => {
                  writer.setAttribute(
                    'emmDrupalMediaUpdated',
                    Date.now(),
                    element,
                  );
                });
              }
            },
            dialogModalSettings
          );
        })
        .catch(error => {
          console.error('Error opening media edit dialog:', error);
          // Optionally show user-friendly error message
          Drupal.Message.error(Drupal.t('Failed to open media editor'));
        });
      });

      return view;
    });
  }

}
