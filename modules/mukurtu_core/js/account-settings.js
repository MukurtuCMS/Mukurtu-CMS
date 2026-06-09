(function (Drupal, once) {
  'use strict';

  /**
   * Enforces generate-password / email-verification mutual exclusion.
   *
   * Genpass values:
   *   0 = "Users must enter a password"    (system cannot generate)
   *   1 = "Users may enter a password"     (system may generate)
   *   2 = "Users cannot enter a password"  (system must generate)
   *
   * Rules:
   *   Email verification ON  → only value 2 is valid; auto-select it.
   *   Admin approval         → only value 0 is valid; auto-select it.
   *   Value 0 or 1 selected  → email verification cannot be turned on.
   *   Value 1 or 2 selected  → admin-approval cannot be selected.
   */
  Drupal.behaviors.mukurtuAccountSettings = {
    attach(context) {
      once('mukurtu-account-settings', '#edit-user-email-verification', context).forEach((verifyMailEl) => {
        const registerAdminApproval = document.getElementById('edit-user-register-visitors-admin-approval');
        const genpassV0 = document.getElementById('edit-genpass-mode-0'); // must enter
        const genpassV1 = document.getElementById('edit-genpass-mode-1'); // may enter
        const genpassV2 = document.getElementById('edit-genpass-mode-2'); // cannot enter

        function setDisabled(el, state) {
          if (el) el.disabled = state;
        }

        function syncState() {
          const verifyChecked = verifyMailEl.checked;
          const approvalRequired = registerAdminApproval?.checked ?? false;

          if (verifyChecked) {
            // Email verification ON: only value 2 ("cannot enter") is valid.
            setDisabled(genpassV0, true);
            setDisabled(genpassV1, true);
            setDisabled(genpassV2, false);
            if ((genpassV0?.checked || genpassV1?.checked) && genpassV2) {
              genpassV2.checked = true;
            }
          }
          else if (approvalRequired) {
            // Admin approval (verify OFF): only value 0 ("must enter") is valid.
            setDisabled(genpassV0, false);
            setDisabled(genpassV1, true);
            setDisabled(genpassV2, true);
            if ((genpassV1?.checked || genpassV2?.checked) && genpassV0) {
              genpassV0.checked = true;
            }
          }
          else {
            // Open visitor registration with verify OFF: all values valid.
            setDisabled(genpassV0, false);
            setDisabled(genpassV1, false);
            setDisabled(genpassV2, false);
          }

          // Re-evaluate state after any auto-switch above.
          const mustOrMayEnter = genpassV0?.checked || genpassV1?.checked || false;
          const mayOrCannotEnter = genpassV1?.checked || genpassV2?.checked || false;

          // Values 0 or 1 are incompatible with email verification being on.
          verifyMailEl.disabled = mustOrMayEnter;
          // Values 1 or 2 are incompatible with admin approval being selected.
          setDisabled(registerAdminApproval, mayOrCannotEnter);
        }

        [verifyMailEl, registerAdminApproval, genpassV0, genpassV1, genpassV2]
          .filter(Boolean)
          .forEach(el => el.addEventListener('change', syncState));

        syncState();
      });
    },
  };

}(Drupal, once));
