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
   *   Email verification OFF → all genpass modes available.
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

        function setDisabled(el, state) {
          if (el) el.disabled = state;
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
            verifyMailEl.checked  = false;
            verifyMailEl.disabled = true;
            setDisabled(registerApproval, false);
            return;
          }

          // Email verification is irrelevant when admin must approve each account.
          if (approvalRequired) {
            verifyMailEl.checked  = false;
            verifyMailEl.disabled = true;
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
          }
          else {
            // All genpass values available (open registration or admin approval).
            setDisabled(genpassV0, false);
            setDisabled(genpassV1, false);
            setDisabled(genpassV2, false);
          }

          // Re-enable email verification if we're not in a restricted mode.
          // (Email verification is always clickable; turning it on auto-locks genpass to 2.)
          if (!approvalRequired) {
            verifyMailEl.disabled = false;
          }
        }

        [verifyMailEl, registerAdminOnly, registerApproval, registerVisitors, genpassV0, genpassV1, genpassV2]
          .filter(Boolean)
          .forEach(el => el.addEventListener('change', syncState));

        syncState();
      });
    },
  };

}(Drupal, once));
