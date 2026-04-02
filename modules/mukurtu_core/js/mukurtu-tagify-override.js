(function (Drupal) {
  /**
   * Update a Tagify instance to stop creating terms on delimiter input.
   *
   * We intentionally disable delimiter-based tokenization so commas can be
   * part of a term value (e.g. "Wynne, Michael"), while Enter still commits.
   */
  function patchTagifyInstance(instance) {
    if (!instance || instance.__mukurtuPatched) {
      return;
    }

    instance.settings.delimiters = null;
    instance.__mukurtuPatched = true;
  }

  /**
   * Find Tagify instances in the current context and patch them.
   */
  function patchTagifyInContext(context) {
    const root = context && context.querySelectorAll ? context : document;
    const inputs = root.querySelectorAll('input, textarea');

    inputs.forEach((element) => {
      // Tagify stores the instance reference on the original element.
      if (element.__tagify) {
        patchTagifyInstance(element.__tagify);
      }
    });
  }

  Drupal.behaviors.mukurtuTagifyOverride = {
    attach(context) {
      // Run immediately and again at end-of-tick in case Tagify initializes
      // later in the same attach cycle.
      patchTagifyInContext(context);
      setTimeout(() => patchTagifyInContext(context), 0);
    },
  };
})(Drupal);
