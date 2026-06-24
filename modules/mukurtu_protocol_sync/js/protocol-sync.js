(function (Drupal, once) {
  'use strict';

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
        var fieldsetWrapper = protocolsWrapper ? protocolsWrapper.querySelector('.fieldset-wrapper') : null;
        if (!fieldsetWrapper) {
          return;
        }

        // Move the sync form-item into the top of the protocols fieldset-wrapper.
        fieldsetWrapper.insertBefore(formItem, fieldsetWrapper.firstElementChild);
        if (syncFieldWrapper) {
          syncFieldWrapper.style.display = 'none';
        }

        // Insert the inherited-protocols message immediately after the form-item.
        var message = document.createElement('p');
        message.className = 'mukurtu-protocol-sync-message';
        message.textContent = Drupal.t('Cultural protocols and sharing setting are synced with the parent content.');
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
            message.style.display = '';
          }
          else {
            targets.forEach(function (el) { el.style.display = ''; });
            message.style.display = 'none';
          }
        }

        toggle();
        checkbox.addEventListener('change', toggle);
      });
    }
  };

})(Drupal, once);
