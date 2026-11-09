/**
 * @file
 * Keeps keyboard focus in a file field after an upload or removal.
 */

((Drupal, tabbable) => {
  /**
   * Moves focus back into the file field that sent the request.
   *
   * The response replaces the field, and the button or file input that had
   * focus goes with it. Core then tries to focus the field's wrapper, which
   * can't take focus, so focus falls to the page body (#2648).
   *
   * @param {Drupal.Ajax} ajax
   *   The Ajax object that sent the request.
   */
  Drupal.AjaxCommands.prototype.mukurtuFocusFileField = (ajax) => {
    const active = document.activeElement;
    // Leave focus alone if it is still on something in the page, for example
    // where another command in the same response has put it.
    if (active && active !== document.body && active.isConnected) {
      return;
    }
    const wrapper = ajax.wrapper ? document.querySelector(ajax.wrapper) : null;
    if (!wrapper) {
      return;
    }
    // A field with several files marks the one just added.
    const container = wrapper.querySelector('.ajax-new-content') || wrapper;
    const [first] = tabbable.tabbable(container);
    if (first) {
      first.focus();
    }
  };
})(Drupal, window.tabbable);
