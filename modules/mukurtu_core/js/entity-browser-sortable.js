/**
 * @file
 * Shows a fully opaque copy of an Entity Browser item while it is dragged.
 *
 * Entity Browser makes its lists sortable with Sortable's defaults, which use
 * native HTML5 drag and drop. The browser then draws its own translucent
 * snapshot of the item as the drag preview, and nothing can make that image
 * opaque, so editors reordering a collection could barely see what they were
 * moving (#808). Sortable's fallback mode drags a real cloned element
 * instead, which entity-browser-sortable.css keeps fully opaque.
 *
 * Sortable picks native or fallback dragging once, in its constructor, so
 * setting forceFallback on an existing instance has no effect. Each instance
 * is recreated instead, carrying over what Entity Browser set: the draggable
 * selector and its event handlers, including the onEnd that saves the new
 * order. The rest of an instance's options can't be passed back in, because
 * Sortable's plugins have already filled them with values their setters
 * reject.
 *
 * Appended to entity_browser/entity_reference by
 * mukurtu_core_library_info_alter(), so this file loads right after
 * entity_browser.entity_reference.js and its behavior attaches after the one
 * that creates the Sortable instances.
 */
((Drupal, Sortable) => {
  Drupal.behaviors.mukurtuEntityBrowserSortable = {
    attach(context) {
      context
        .querySelectorAll(
          '.field--widget-entity-browser-entity-reference .entities-list.sortable',
        )
        .forEach((list) => {
          const sortable = Sortable.get(list);
          if (!sortable || sortable.options.forceFallback) {
            return;
          }
          const options = Object.fromEntries(
            Object.entries(sortable.options).filter(
              ([name, value]) =>
                name === 'draggable' ||
                (name.startsWith('on') && typeof value === 'function'),
            ),
          );
          options.forceFallback = true;

          // The copy is a full clone of the item, made just before the start
          // event, so for the length of the drag it would repeat the item's
          // IDs and add a second focusable Remove button. It is only a visual
          // stand-in, so take it out of the accessibility tree and focus
          // order, and drop its IDs.
          const { onStart } = options;
          options.onStart = (event) => {
            const copy = Sortable.ghost;
            if (copy) {
              copy.inert = true;
              copy.setAttribute('aria-hidden', 'true');
              copy.removeAttribute('id');
              copy.querySelectorAll('[id]').forEach((element) => {
                element.removeAttribute('id');
              });
            }
            if (onStart) {
              onStart(event);
            }
          };

          sortable.destroy();
          Sortable.create(list, options);
        });
    },
  };
})(Drupal, Sortable);
