/**
 * @file
 * Keeps Tagify's suggestion list inside its field (#2721).
 *
 * Tagify adds the list to the end of <body>, outside every landmark, so
 * screen reader users moving by landmark couldn't reach it. Tagify can add
 * it to the field's own wrapper instead, which keeps it in the form's
 * landmark and next to the field in reading order. Its setting is read each
 * time the list opens, so it can be changed after the module creates the
 * field. css/tagify-list-placement.css corrects the list's position there.
 */
((Drupal) => {
  /**
   * The nearest ancestor that scrolls or clips, such as a dialog's content.
   */
  function scrollContainerOf(element) {
    for (let parent = element.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
      const style = getComputedStyle(parent);
      if (/auto|scroll|hidden|clip/.test(`${style.overflowY} ${style.overflowX}`)) {
        return parent;
      }
    }
    return null;
  }

  /**
   * Opens the list above the field when there's no room for it below.
   *
   * Tagify measures the room against the window. Inside a dialog the list
   * now scrolls with the dialog's content, so near the dialog's bottom edge
   * it opened behind the dialog's buttons, hiding options that the arrow
   * keys could still highlight. Measure against the field's scrolling
   * container as well.
   */
  function placeWithinContainer(instance) {
    const { position } = instance.dropdown;
    instance.dropdown.position = (height, ...rest) => {
      const listHeight = height || instance.DOM.dropdown.clientHeight;
      const field = instance.DOM.scope.getBoundingClientRect();
      const container = scrollContainerOf(instance.DOM.scope)?.getBoundingClientRect();
      const top = Math.max(container ? container.top : 0, 0);
      const bottom = Math.min(container ? container.bottom : window.innerHeight, window.innerHeight);
      const below = bottom - field.bottom;
      instance.settings.dropdown.placeAbove = below < listHeight && field.top - top > below;
      return position(height, ...rest);
    };
  }

  Drupal.behaviors.mukurtuTagifyListPlacement = {
    attach(context) {
      const place = () => {
        const root = context && context.querySelectorAll ? context : document;
        root.querySelectorAll('input, textarea').forEach((element) => {
          const instance = element.__tagify;
          if (instance && instance.settings.dropdown.appendTarget !== instance.DOM.scope) {
            instance.settings.dropdown.appendTarget = instance.DOM.scope;
            placeWithinContainer(instance);
          }
        });
      };
      place();
      // The Tagify module may create fields later in the same attach cycle.
      setTimeout(place, 0);
    },
  };
})(Drupal);
