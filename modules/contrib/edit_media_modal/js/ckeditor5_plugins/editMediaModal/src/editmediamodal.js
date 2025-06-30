import { Plugin, icons } from 'ckeditor5/src/core';
import { ButtonView } from 'ckeditor5/src/ui';

export default class EditMediaModal extends Plugin {

  constructor(editor) {
    super(editor);

    // Add our custom attribute to the media plugins converterAttributes.
    // This triggers rerendering when the attribute changes.
    const mediaEditing = editor.plugins.get('DrupalMediaEditing');
    mediaEditing.converterAttributes.push('emmDrupalMediaUpdated')
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

  _createButton() {
    const editor = this.editor;
    const options = this.editor.config.get('editMediaModal');
    const { openDialog, dialogSettings = {} } = options;

    editor.ui.componentFactory.add('editMediaButton', (locale) => {
      const view = new ButtonView(locale);

      view.set({
        label: Drupal.t('Edit media'),
        icon: icons.pencil,
        tooltip: true,
      });

      this.listenTo(view, 'execute', (eventInfo) => {
        const element = this.editor.model.document.selection.getSelectedElement();
        const uuid = element.getAttribute('drupalMediaEntityUuid');

        fetch(Drupal.url(`edit-media-modal/edit-url/${uuid}`))
          .then(response => response.json())
          .then((data) => {
            openDialog(
              data.url,
              () => {
                const element = this.editor.model.document.selection.getSelectedElement();
                if (element && typeof element.name !== "undefined" && element.name === 'drupalMedia') {
                  this.editor.model.change(writer => {
                    writer.setAttribute(
                      'emmDrupalMediaUpdated',
                      Date.now(),
                      element,
                    );
                  });
                }
              },
              dialogSettings
            );
          })
      });

      return view;
    });
  }

}
