(function (Drupal, once) {
  'use strict';

  /**
   * Enforces mutual exclusion between incompatible account registration options.
   *
   * "May" and "Must" generate-password modes are only valid when registration
   * is open to visitors (not admin approval or admin-only) and email
   * verification is disabled. When either of those conditions is active, the
   * two modes are disabled; conversely, selecting May or Must locks out the
   * verify-mail checkbox and the admin-approval radio.
   */
  Drupal.behaviors.mukurtuAccountSettings = {
    attach(context) {
      once('mukurtu-account-settings', '#user-admin-settings', context).forEach(() => {
        const verifyMail = document.getElementById('edit-user-email-verification');
        const registerVisitors = document.getElementById('edit-user-register-visitors');
        const registerAdminApproval = document.getElementById('edit-user-register-visitors-admin-approval');
        const registerAdminOnly = document.getElementById('edit-user-register-admin-only');
        const genpassMay = document.getElementById('edit-genpass-mode-1');
        const genpassMust = document.getElementById('edit-genpass-mode-2');

        function syncState() {
          const verifyChecked = verifyMail?.checked ?? false;
          const approvalRequired = registerAdminApproval?.checked || registerAdminOnly?.checked || false;
          const genpassOptional = genpassMay?.checked || genpassMust?.checked || false;

          // May/Must genpass only valid when register=visitors AND verify_mail=off.
          const blockGenpass = verifyChecked || approvalRequired;
          if (genpassMay) genpassMay.disabled = blockGenpass;
          if (genpassMust) genpassMust.disabled = blockGenpass;

          // When May or Must genpass is selected, verify_mail and admin-approval
          // are incompatible and must be disabled.
          if (verifyMail) verifyMail.disabled = genpassOptional;
          if (registerAdminApproval) registerAdminApproval.disabled = genpassOptional;
        }

        [verifyMail, registerVisitors, registerAdminApproval, registerAdminOnly, genpassMay, genpassMust]
          .filter(Boolean)
          .forEach(el => el.addEventListener('change', syncState));

        syncState();
      });
    },
  };

}(Drupal, once));
