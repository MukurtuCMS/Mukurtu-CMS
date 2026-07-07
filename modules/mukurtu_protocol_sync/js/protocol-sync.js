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
      // Guard on the form rather than the button itself. Listeners bound to
      // the same target element fire in registration order regardless of the
      // capture flag, so a capture listener on the button cannot reliably
      // beat Drupal core's own AJAX listener bound to that same button. A
      // capture listener on an ancestor (the form) is guaranteed to run
      // first, since it fires during the capture phase before the event
      // reaches the button.
      once('mukurtu-ps-node-form', 'form[id*="node-"]', context).forEach(function (nodeForm) {
        function protocolsSelected() {
          var protocolWrapper = nodeForm.querySelector('[id*="field-cultural-protocols"][id$="-wrapper"]');
          if (!protocolWrapper) {
            return true; // can't verify, don't block
          }
          var hasProtocol = protocolWrapper.querySelector('input[type="checkbox"]:checked');
          var hasSharing = protocolWrapper.querySelector('input[type="radio"]:checked');
          return !!(hasProtocol && hasSharing);
        }

        function guard(event) {
          var btn = event.target.closest('.js-media-library-open-button');
          if (!btn || !nodeForm.contains(btn) || protocolsSelected()) {
            return;
          }
          // Drupal core's AJAX behavior triggers keyboard activation (Enter
          // and Space) by calling its response handler directly from its own
          // 'keypress' listener, not by dispatching a click, so keypress must
          // be guarded here too. Ignore keypresses that aren't an activation.
          if (event.type === 'keypress' && event.key !== 'Enter' && event.key !== ' ') {
            return;
          }
          event.preventDefault();
          event.stopPropagation();
          // Show the message on click and keypress (completed interactions),
          // not on mousedown, so the dismiss handler (which listens for the
          // next click) isn't triggered by the same interaction that opened it.
          if (event.type === 'click' || event.type === 'keypress') {
            _showProtocolRequiredMessage(btn);
          }
        }

        nodeForm.addEventListener('mousedown', guard, true);
        nodeForm.addEventListener('click', guard, true);
        nodeForm.addEventListener('keypress', guard, true);
      });
    }
  };

  // Tracks the currently displayed "protocols required" message so a new
  // one (from any field's button) always replaces the previous one instead
  // of stacking duplicate messages and dismiss listeners.
  var activeProtocolMessage = null;

  function _showProtocolRequiredMessage(btn) {
    if (activeProtocolMessage) {
      activeProtocolMessage.remove();
    }

    var container = btn.closest('.js-media-library-widget') || btn.parentElement;

    var el = document.createElement('div');
    el.className = 'mukurtu-ps-required-message messages messages--warning';
    el.setAttribute('role', 'alert');
    el.setAttribute('tabindex', '-1');
    el.textContent = Drupal.t(
      'To sync media cultural protocols, the content must have protocols assigned first. Select content protocols, then add media.'
    );
    container.insertAdjacentElement('afterend', el);
    el.focus();

    function remove() {
      el.remove();
      document.removeEventListener('click', dismissClick, true);
      document.removeEventListener('keydown', dismissKey, true);
      activeProtocolMessage = null;
    }

    // Dismiss on the next click outside the message.
    function dismissClick(e) {
      if (!el.contains(e.target)) {
        remove();
      }
    }

    // Dismiss on Escape for keyboard users.
    function dismissKey(e) {
      if (e.key === 'Escape') {
        remove();
        btn.focus();
      }
    }

    document.addEventListener('click', dismissClick, true);
    document.addEventListener('keydown', dismissKey, true);

    activeProtocolMessage = {remove: remove};
  }

})(Drupal, once);
