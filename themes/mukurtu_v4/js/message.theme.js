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

    // WCAG 4.1.3: role="contentinfo" above is a landmark, not a live region,
    // so screen readers won't announce this without its own role here.
    const liveRegionRole = type === 'status' ? 'status' : 'alert';
    wrapper.innerHTML = `
      <div class="messages__container" data-drupal-selector="messages-container" role="${liveRegionRole}">
        <div class="messages__header">
          <h2 class="visually-hidden">${messageTypes[type]}</h2>
        </div>
        <div class="messages__content">${text}</div>
        <button type="button" class="messages__close" data-drupal-selector="messages-close">
          <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M2 2l12 12M14 2L2 14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span class="visually-hidden"></span>
        </button>
      </div>
    `;
    wrapper.querySelector('.messages__close .visually-hidden').textContent =
      Drupal.t('Dismiss message');

    return wrapper;
  };
})(Drupal);
