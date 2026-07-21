(function (Drupal, once) {
  'use strict';

  /**
   * Moves the sync checkbox into the protocols fieldset and toggles visibility.
   *
   * Runs inside the media library modal after a file is uploaded (AJAX
   * context), on standalone media add/edit forms, and on the bulk media
   * upload/create forms. In the media library modal (opened from a content
   * page) protocols are typically already inherited, so the toggle goes to
   * the top of the fieldset. Elsewhere protocols are never pre-populated, so
   * the toggle goes after protocol selection instead (see the
   * data-mukurtu-protocol-sync-position attribute).
   */
  Drupal.behaviors.mukurtuProtocolSync = {
    attach: function (context, settings) {
      once('mukurtu-protocol-sync', '[data-mukurtu-protocol-sync]', context).forEach(function (checkbox) {
        var formItem = checkbox.closest('.form-item');
        if (!formItem) {
          return;
        }

        // The sync checkbox renders inside its own field wrapper. Walk up to
        // find that wrapper, then find its parent (the shared fields container
        // for this media item). The cultural protocols wrapper is a sibling.
        var syncFieldWrapper = formItem.closest('[id*="field-sync-protocols-wrapper"]');
        var fieldsContainer = syncFieldWrapper ? syncFieldWrapper.parentElement : null;

        // For standalone media edit forms, the sync and protocol wrappers share
        // a common ancestor but may not use the media-item fields container;
        // fall back to the form element itself.
        if (!fieldsContainer) {
          fieldsContainer = formItem.closest('form');
        }
        if (!fieldsContainer) {
          return;
        }

        var protocolsWrapper = fieldsContainer.querySelector('[id*="field-cultural-protocols-wrapper"]');
        // Gin admin theme uses BEM class "fieldset__wrapper"; Stable uses "fieldset-wrapper".
        var fieldsetWrapper = protocolsWrapper
          ? protocolsWrapper.querySelector('.fieldset__wrapper, .fieldset-wrapper')
          : null;
        if (!fieldsetWrapper) {
          return;
        }

        // Move the sync form-item into the protocols fieldset-wrapper: to the
        // top when protocols are expected to already be filled in (opened
        // from a content page), or after protocol selection when they aren't
        // (standalone and bulk forms).
        if (checkbox.dataset.mukurtuProtocolSyncPosition === 'after') {
          fieldsetWrapper.appendChild(formItem);
        }
        else {
          fieldsetWrapper.insertBefore(formItem, fieldsetWrapper.firstElementChild);
        }
        if (syncFieldWrapper) {
          syncFieldWrapper.style.display = 'none';
        }

        // Insert the inherited-protocols message immediately after the form-item.
        var message = document.createElement('p');
        message.className = 'mukurtu-protocol-sync-message';
        message.setAttribute('aria-live', 'polite');
        message.style.display = 'none';
        formItem.insertAdjacentElement('afterend', message);

        // All siblings in the fieldset-wrapper except the sync form-item and
        // the message are the protocol/sharing elements to show or hide.
        function getSyncTargets() {
          return Array.from(fieldsetWrapper.children).filter(function (el) {
            return el !== formItem && el !== message;
          });
        }

        function toggle() {
          var targets = getSyncTargets();
          if (checkbox.checked) {
            targets.forEach(function (el) { el.style.display = 'none'; });
            // Set the text at the same time the region becomes visible so
            // assistive tech observes a real content mutation and reliably
            // announces it (toggling display alone on unchanging text is
            // not consistently announced across screen readers).
            message.textContent = Drupal.t('Cultural protocols and sharing setting are synced with the parent content.');
            message.style.display = '';
          }
          else {
            targets.forEach(function (el) { el.style.display = ''; });
            message.style.display = 'none';
            message.textContent = '';
          }
        }

        toggle();
        checkbox.addEventListener('change', toggle);
      });
    }
  };

})(Drupal, once);
