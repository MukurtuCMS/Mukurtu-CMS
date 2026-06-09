(function (Drupal, once) {
  'use strict';

  /**
   * Enforces the email-verification / generate-password mutual exclusion.
   *
   * When email verification is ON:
   *   - "May" and "Must" generate-password modes are disabled (Cannot is the
   *     only valid choice).
   *
   * When "May" or "Must" is selected:
   *   - The email verification checkbox is disabled.
   *
   * "Visitors, but administrator approval is required" also makes May and Must
   * unavailable, since that combination is not supported.
   *
   * "Administrators only" is N/A for generate-password — no restriction.
   */
  Drupal.behaviors.mukurtuAccountSettings = {
    attach(context) {
      // Attach once per page, keyed to the verify-mail checkbox. If that
      // element doesn't exist we're on a different form and can bail early.
      once('mukurtu-account-settings', '#edit-user-email-verification', context).forEach((verifyMailEl) => {
        const registerAdminApproval = document.getElementById('edit-user-register-visitors-admin-approval');
        const genpassCannot = document.getElementById('edit-genpass-mode-0');
        const genpassMay = document.getElementById('edit-genpass-mode-1');
        const genpassMust = document.getElementById('edit-genpass-mode-2');

        function syncState() {
          const verifyChecked = verifyMailEl.checked;
          const approvalRequired = registerAdminApproval?.checked ?? false;
          const genpassOptional = (genpassMay?.checked || genpassMust?.checked) ?? false;

          // May/Must are invalid when email verification is on, or when admin
          // approval is required (regardless of verify setting).
          const blockGenpass = verifyChecked || approvalRequired;
          if (genpassMay) genpassMay.disabled = blockGenpass;
          if (genpassMust) genpassMust.disabled = blockGenpass;

          // When May or Must is active, lock out email verification and the
          // admin-approval radio to prevent saving an invalid combination.
          verifyMailEl.disabled = genpassOptional;
          if (registerAdminApproval) registerAdminApproval.disabled = genpassOptional;
        }

        // Include genpassCannot so switching back from May/Must to Cannot
        // re-evaluates and re-enables the verify checkbox.
        [verifyMailEl, registerAdminApproval, genpassCannot, genpassMay, genpassMust]
          .filter(Boolean)
          .forEach(el => el.addEventListener('change', syncState));

        syncState();
      });
    },
  };

}(Drupal, once));
