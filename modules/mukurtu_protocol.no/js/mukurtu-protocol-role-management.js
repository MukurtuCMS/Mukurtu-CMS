(function ($) {
  $(document).ready(function () {
    $('div input[type="checkbox"][id*="edit-protocol-memberships"]').click(function () {
      if (this.id.endsWith('node-protocol-non-member')) {
        const rowId = this.id.replace('-node-protocol-non-member', '');

        // Non-member selected, uncheck all member roles.
        if (this.checked) {
          $(`input[type="checkbox"][id*="${rowId}"]`).each(function () {
            if (!this.id.endsWith('node-protocol-non-member')) {
              this.checked = false;
            }
          });
        } else {
          // Non-member unselected, check member.
          let memberId = this.id.replace('node-protocol-non-member', 'node-protocol-member');
          $(`#${memberId}`)[0].checked = true;
        }
      } else {
        if (this.checked) {
          // Some sort of member role selected, uncheck non-member, check member.
          const regex = /-node-protocol-.*/i;
          let baseId = this.id.replace(regex, '');
          $(`#${baseId}-node-protocol-non-member`)[0].checked = false;
          $(`#${baseId}-node-protocol-member`)[0].checked = true;
        } else {
          // Member was unchecked, clear all roles and check non-member.
          if (this.id.endsWith('node-protocol-member')) {
            const rowId = this.id.replace('-node-protocol-member', '');

            // Clear all roles.
            $(`input[type="checkbox"][id*="${rowId}"]`).each(function () {
              if (!this.id.endsWith('node-protocol-non-member')) {
                this.checked = false;
              }
            });

            // Check non-member.
            let nonMemberId = this.id.replace('node-protocol-member', 'node-protocol-non-member');
            $(`#${nonMemberId}`)[0].checked = true;
          }
        }
      }
    });
  });
}(jQuery));
