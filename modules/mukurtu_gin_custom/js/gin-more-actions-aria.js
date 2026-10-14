/**
 * @file
 * Corrects the ARIA on Gin's "More actions" disclosure trigger.
 *
 * Two WCAG 4.1.2 defects in Gin's own markup, both confirmed against
 * drupal/gin 5.0.15 on a node edit form:
 *
 * 1. The trigger carries aria-controls="gin_more_actions", and no element
 *    with that id exists on the page. The menu it actually opens is
 *    #edit-more-actions-items, inside the #edit-more-actions wrapper. An
 *    aria-controls that resolves to nothing is worse than none at all: a
 *    screen reader announces a relationship the user cannot follow.
 *
 * 2. aria-expanded is absent while the menu is closed, and Gin only adds
 *    it when the menu opens. So the collapsed state - the state the user
 *    meets first - does not announce as a disclosure at all.
 *
 * Tracked upstream as Gin #3500065 (open, labeled wcag412). This is a
 * local override rather than a composer patch because, unlike the toolbar
 * fix carried in PR #2225, there is no reviewed upstream merge request to
 * carry: the fix here is ours, and an override is easier to drop when Gin
 * ships its own than a patch that will conflict.
 *
 * Deliberately conservative. It only rewrites aria-controls when the
 * current value does not resolve, so if Gin fixes this upstream the
 * override stops touching it rather than fighting the new markup.
 *
 * @see https://git.drupalcode.org/project/gin/-/work_items/3500065
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2248
 */
((Drupal, once) => {
  /**
   * Finds the menu a trigger opens, within its own wrapper.
   *
   * @param {HTMLElement} trigger
   *   The "More actions" trigger.
   *
   * @return {HTMLElement|null}
   *   The menu element, or null if the markup is not what we expect.
   */
  const findMenu = (trigger) => {
    const wrapper = trigger.closest('.gin-more-actions');
    if (!wrapper) {
      return null;
    }
    // Gin ids the items container from the form element name, so it is
    // the wrapper's id with an -items suffix. Fall back to the first
    // descendant carrying an id, rather than guessing at a class name
    // that a future Gin release could rename.
    return wrapper.querySelector(`#${CSS.escape(`${wrapper.id}-items`)}`)
      || wrapper.querySelector('[id]');
  };

  Drupal.behaviors.mukurtuGinMoreActionsAria = {
    attach(context) {
      once('mukurtu-gin-more-actions-aria', '.gin-more-actions__trigger', context).forEach((trigger) => {
        const controls = trigger.getAttribute('aria-controls');

        // Only intervene when the current value points at nothing. If Gin
        // starts emitting a resolvable id, leave it alone.
        if (!controls || !document.getElementById(controls)) {
          const menu = findMenu(trigger);
          if (menu && menu.id) {
            trigger.setAttribute('aria-controls', menu.id);
          }
          else {
            // Nothing to point at. A dangling reference is worse than an
            // absent one, so remove it rather than leave it broken.
            trigger.removeAttribute('aria-controls');
          }
        }

        // Give the collapsed state a value. Gin sets this to "true" on
        // open but never initialises it, so without this the trigger does
        // not announce as a disclosure until after it has been used once.
        if (!trigger.hasAttribute('aria-expanded')) {
          trigger.setAttribute('aria-expanded', trigger.classList.contains('is-active') ? 'true' : 'false');
        }
      });
    },
  };
})(Drupal, once);
