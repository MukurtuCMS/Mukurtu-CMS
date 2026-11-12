/**
 * @file
 * Clears the file field of an upload the server refused, and announces why.
 */

((Drupal) => {
  /**
   * Clears the field that sent the request, and its earlier messages.
   *
   * @param {Drupal.Ajax} ajax
   *   The Ajax object that sent the upload.
   * @param {object} response
   *   The command from the server.
   * @param {string} response.message
   *   The text to announce.
   */
  Drupal.AjaxCommands.prototype.mukurtuResetRefusedUpload = (
    ajax,
    response,
  ) => {
    Drupal.announce(response.message, 'assertive');
    const wrapper = ajax.wrapper ? document.querySelector(ajax.wrapper) : null;
    if (!wrapper) {
      return;
    }
    wrapper.querySelectorAll('input[type="file"]').forEach((input) => {
      input.value = '';
    });
    wrapper
      .querySelectorAll('[data-drupal-messages]')
      .forEach((messages) => messages.remove());
  };
})(Drupal);
