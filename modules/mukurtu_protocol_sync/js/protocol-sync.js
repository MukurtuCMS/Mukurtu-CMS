(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.mukurtuProtocolSync = {
    attach: function (context, settings) {
      once('mukurtu-protocol-sync', '[data-mukurtu-protocol-sync]', context).forEach(function (checkbox) {
        // Find the .fieldset-wrapper that is the direct parent container of the
        // sync checkbox within the cultural protocols fieldset.
        var fieldsetWrapper = checkbox.closest('.fieldset-wrapper');
        if (!fieldsetWrapper) {
          return;
        }

        var formItem = checkbox.closest('.form-item');

        // Insert an informational note immediately after the sync checkbox.
        var message = document.createElement('p');
        message.className = 'mukurtu-protocol-sync-message';
        message.textContent = Drupal.t('Cultural protocols and sharing setting are synced with the parent content.');
        message.style.display = 'none';
        if (formItem) {
          formItem.insertAdjacentElement('afterend', message);
        }

        // All direct children of the fieldset-wrapper except the sync checkbox
        // form-item and the message are the protocol/sharing elements to toggle.
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
