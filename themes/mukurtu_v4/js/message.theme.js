/**
 * @file
 * Overrides core's message theme function to match this theme's
 * status-messages.html.twig markup, so AJAX-inserted messages (e.g. the
 * quick-action "stay" dialog flow) render with the same wrapper structure
 * and styling as server-rendered ones.
 */

((Drupal) => {
  Drupal.theme.message = ({ text }, { type, id }) => {
    const messageTypes = Drupal.Message.getMessageTypeLabels();
    const wrapper = document.createElement('div');

    wrapper.setAttribute(
      'class',
      `messages-list__item messages messages--${type}`,
    );
    wrapper.setAttribute('data-drupal-selector', 'messages');
    wrapper.setAttribute('role', 'contentinfo');
    wrapper.setAttribute('aria-label', messageTypes[type]);
    wrapper.setAttribute('data-drupal-message-id', id);

    wrapper.innerHTML = `
      <div class="messages__container" data-drupal-selector="messages-container"${type === 'error' ? ' role="alert"' : ''}>
        <div class="messages__header">
          <h2 class="visually-hidden">${messageTypes[type]}</h2>
        </div>
        <div class="messages__content">${text}</div>
      </div>
    `;

    return wrapper;
  };
})(Drupal);
