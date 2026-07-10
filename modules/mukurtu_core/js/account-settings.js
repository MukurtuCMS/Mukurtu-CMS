(function (Drupal, once) {
  'use strict';

  /**
   * Enforces generate-password / email-verification / registration-mode rules.
   *
   * Genpass values:
   *   0 = "Users must enter a password"    (system cannot generate)
   *   1 = "Users may enter a password"     (system may generate)
   *   2 = "Users cannot enter a password"  (system must generate)
   *
   * Dependency chain (top to bottom):
   *   Admin-only → genpass mode disabled (no registration form); strength stays enabled.
   *   Admin-approval → email verification disabled; all genpass modes remain available.
   *   Email verification ON → genpass locked to 2 (auto-switched if needed).
   *   Email verification OFF, no approval required → genpass value 1 disabled
   *     (auto-switched to 0 if needed); values 0 and 2 remain available.
   *   Email verification OFF, approval required → all genpass modes available.
   */
  Drupal.behaviors.mukurtuAccountSettings = {
    attach(context) {
      once('mukurtu-account-settings', '#edit-user-email-verification', context).forEach((verifyMailEl) => {
        const registerAdminOnly = document.getElementById('edit-user-register-admin-only');
        const registerApproval  = document.getElementById('edit-user-register-visitors-admin-approval');
        const registerVisitors  = document.getElementById('edit-user-register-visitors');
        const genpassV0         = document.getElementById('edit-genpass-mode-0'); // must enter
        const genpassV1         = document.getElementById('edit-genpass-mode-1'); // may enter
        const genpassV2         = document.getElementById('edit-genpass-mode-2'); // cannot enter
        const passwordStrength  = document.getElementById('edit-user-password-strength');

        // Inject a live region so screen readers are notified when form
        // constraints change (WCAG 4.1.3 Status Messages).
        const liveRegion = document.createElement('div');
        liveRegion.setAttribute('role', 'status');
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.className = 'visually-hidden';
        verifyMailEl.closest('form')?.appendChild(liveRegion);

        // Suppress announcements during the initial attach run so the existing
        // saved state is not narrated on every page load.
        let initializing = true;

        function setDisabled(el, state) {
          if (!el) return;
          el.disabled = state;
          // aria-disabled keeps the element in the accessibility tree so
          // screen readers can announce the option and explain it is unavailable.
          el.setAttribute('aria-disabled', state ? 'true' : 'false');
        }

        function announce(message) {
          if (initializing) return;
          // Clear first so repeated identical messages still trigger a re-read.
          liveRegion.textContent = '';
          setTimeout(() => { liveRegion.textContent = message; }, 50);
        }

        function syncState() {
          const adminOnly        = registerAdminOnly?.checked ?? false;
          const approvalRequired = registerApproval?.checked ?? false;

          if (adminOnly) {
            // No visitor registration form — genpass mode and email verification
            // are irrelevant, but password strength still helps admins setting passwords.
            setDisabled(genpassV0, true);
            setDisabled(genpassV1, true);
            setDisabled(genpassV2, true);
            setDisabled(passwordStrength, false);
            setDisabled(verifyMailEl, true);
            verifyMailEl.checked = false;
            setDisabled(registerApproval, false);
            announce(Drupal.t('Registration is restricted to administrators. Password settings and email verification do not apply.'));
            return;
          }

          // Email verification is irrelevant when admin must approve each account.
          if (approvalRequired) {
            setDisabled(verifyMailEl, true);
            verifyMailEl.checked = false;
            announce(Drupal.t('Email verification is disabled when administrator approval is required.'));
          }
          else {
            setDisabled(verifyMailEl, false);
          }

          const verifyChecked = verifyMailEl.checked;

          if (verifyChecked) {
            // Email verification ON: only genpass value 2 ("cannot enter") is valid.
            setDisabled(genpassV0, true);
            setDisabled(genpassV1, true);
            setDisabled(genpassV2, false);
            if ((genpassV0?.checked || genpassV1?.checked) && genpassV2) {
              genpassV2.checked = true;
            }
            announce(Drupal.t('Email verification is enabled. Visitor password entry is set to automatic generation.'));
          }
          else if (registerVisitors?.checked) {
            // No approval and no email verification: value 1 ("may enter") is
            // unsafe because a blank submission logs the visitor in with a
            // password they never see and can't recover after logging out.
            setDisabled(genpassV0, false);
            setDisabled(genpassV1, true);
            setDisabled(genpassV2, false);
            if (genpassV1?.checked && genpassV0) {
              genpassV0.checked = true;
            }
            announce(Drupal.t('Visitors can register without approval and email verification is disabled. Visitor password entry is set to require a password.'));
          }
          else {
            // All genpass values available (admin approval required).
            setDisabled(genpassV0, false);
            setDisabled(genpassV1, false);
            setDisabled(genpassV2, false);
          }

          // Re-enable email verification if we're not in a restricted mode.
          // (Email verification is always clickable; turning it on auto-locks genpass to 2.)
          if (!approvalRequired) {
            setDisabled(verifyMailEl, false);
          }
        }

        [verifyMailEl, registerAdminOnly, registerApproval, registerVisitors, genpassV0, genpassV1, genpassV2]
          .filter(Boolean)
          .forEach(el => el.addEventListener('change', syncState));

        syncState();
        initializing = false;
      });
    },
  };

}(Drupal, once));
