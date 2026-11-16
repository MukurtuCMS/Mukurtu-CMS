/**
 * @file
 * Keeps keyboard focus in a file field after an upload or removal.
 */

(($, Drupal, tabbable) => {
  /**
   * Moves focus back into the file field that sent the request.
   *
   * The response replaces the field, and the button or file input that had
   * focus goes with it. Core then tries to focus the field's old wrapper,
   * which no longer exists, so focus falls to the page body (#2648).
   *
   * @param {Drupal.Ajax} ajax
   *   The Ajax object that sent the request.
   * @param {object} response
   *   The command from the server.
   * @param {string} response.selector
   *   The new wrapper of the replaced field.
   */
  Drupal.AjaxCommands.prototype.mukurtuFocusFileField = (ajax, response) => {
    // Leave focus alone if it is still on something in the page, for example
    // where another command in the same response has put it.
    if (document.activeElement && document.activeElement !== document.body) {
      return;
    }
    const wrapper = document.querySelector(response.selector);
    if (!wrapper) {
      return;
    }
    // Prefer the file just added in a field with several files, then the file
    // field itself, over other controls in the wrapper, such as the summary of
    // a details element around the field or a row's drag handle.
    const container =
      wrapper.querySelector(
        '.js-form-managed-file.ajax-new-content, .ajax-new-content .js-form-managed-file',
      ) ||
      wrapper.querySelector('.js-form-managed-file') ||
      wrapper;
    const [first] = tabbable.tabbable(container);
    if (first) {
      first.focus();
      // Stop core refocusing the button that sent the request. In a field
      // with several files, the file that moves up takes that button's
      // data-drupal-selector, so core would move focus to its Remove button.
      if (ajax.element) {
        $(ajax.element).data('disable-refocus', true);
      }
    }
  };
})(jQuery, Drupal, window.tabbable);
