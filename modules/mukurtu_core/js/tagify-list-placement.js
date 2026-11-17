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
  Drupal.behaviors.mukurtuTagifyListPlacement = {
    attach(context) {
      const place = () => {
        const root = context && context.querySelectorAll ? context : document;
        root.querySelectorAll('input, textarea').forEach((element) => {
          const instance = element.__tagify;
          if (instance && instance.settings.dropdown.appendTarget !== instance.DOM.scope) {
            instance.settings.dropdown.appendTarget = instance.DOM.scope;
          }
        });
      };
      place();
      // The Tagify module may create fields later in the same attach cycle.
      setTimeout(place, 0);
    },
  };
})(Drupal);
