(function (Drupal, once) {
  'use strict';

  /**
   * Moves the sync checkbox into the protocols fieldset and toggles visibility.
   *
   * Runs inside the media library modal after a file is uploaded (AJAX context)
   * and on standalone media edit forms.
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

        // Move the sync form-item into the top of the protocols fieldset-wrapper.
        fieldsetWrapper.insertBefore(formItem, fieldsetWrapper.firstElementChild);
        if (syncFieldWrapper) {
          syncFieldWrapper.style.display = 'none';
        }

        // Insert the inherited-protocols message immediately after the form-item.
        var message = document.createElement('p');
        message.className = 'mukurtu-protocol-sync-message';
        message.setAttribute('aria-live', 'polite');
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

  /**
   * Guards the "Add media" button on node forms.
   *
   * If the node form's cultural protocols field has no protocols selected yet,
   * the media library modal is blocked and a message explains what the user
   * must do first. When protocols are selected, the button works normally and
   * the server-side submit handler (see mukurtu_protocol_sync.module) stores
   * the selected protocols in TempStore so the media library can pre-populate
   * the media's protocol field on upload.
   */
  Drupal.behaviors.mukurtuProtocolSyncNodeForm = {
    attach: function (context, settings) {
      once('mukurtu-ps-node-form', '.js-media-library-open-button', context).forEach(function (btn) {
        // Only act on buttons that live inside a node edit/create form.
        var nodeForm = btn.closest('form[id*="node-"]');
        if (!nodeForm) {
          return;
        }

        function protocolsSelected() {
          var protocolWrapper = nodeForm.querySelector('[id*="field-cultural-protocols"][id$="-wrapper"]');
          if (!protocolWrapper) {
            return true; // can't verify, don't block
          }
          var hasProtocol = protocolWrapper.querySelector('input[type="checkbox"]:checked');
          var hasSharing = protocolWrapper.querySelector('input[type="radio"]:checked');
          return !!(hasProtocol && hasSharing);
        }

        // Intercept mousedown in capture phase: Drupal's AJAX sets the
        // triggering element on mousedown, so we must block there first.
        btn.addEventListener('mousedown', function (event) {
          if (!protocolsSelected()) {
            event.preventDefault();
            event.stopImmediatePropagation();
          }
        }, true);

        // Also intercept click in capture phase as a safety net, and show
        // the user-facing message here so the dismiss handler (which listens
        // for the next click) isn't triggered by the same interaction.
        btn.addEventListener('click', function (event) {
          if (!protocolsSelected()) {
            event.preventDefault();
            event.stopImmediatePropagation();
            _showProtocolRequiredMessage(btn);
          }
        }, true);
      });
    }
  };

  function _showProtocolRequiredMessage(btn) {
    var container = btn.closest('.js-media-library-widget') || btn.parentElement;

    // Remove any previously shown message for this widget.
    var sibling = container.nextElementSibling;
    if (sibling && sibling.classList.contains('mukurtu-ps-required-message')) {
      sibling.remove();
    }

    var el = document.createElement('div');
    el.className = 'mukurtu-ps-required-message messages messages--warning';
    el.setAttribute('role', 'alert');
    el.setAttribute('aria-label', Drupal.t('Warning'));
    el.setAttribute('tabindex', '-1');
    el.textContent = Drupal.t(
      'To sync media cultural protocols, the content must have protocols assigned first. Select content protocols, then add media.'
    );
    container.insertAdjacentElement('afterend', el);
    el.focus();

    function removeMessage() {
      el.remove();
      document.removeEventListener('click', dismissClick, true);
      document.removeEventListener('keydown', dismissKey, true);
    }

    // Dismiss on the next click outside the message.
    function dismissClick(e) {
      if (!el.contains(e.target)) {
        removeMessage();
      }
    }

    // Dismiss on Escape for keyboard users.
    function dismissKey(e) {
      if (e.key === 'Escape') {
        removeMessage();
        btn.focus();
      }
    }

    document.addEventListener('click', dismissClick, true);
    document.addEventListener('keydown', dismissKey, true);
  }

})(Drupal, once);
