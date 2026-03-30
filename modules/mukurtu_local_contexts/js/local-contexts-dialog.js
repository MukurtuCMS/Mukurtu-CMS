((Drupal, once) => {
  "use strict";

  Drupal.behaviors.localContextsDialog = {
    attach(context) {
      once('local-contexts-dialog', '.local-contexts-dialog', context).forEach((wrapper) => {
        const trigger = wrapper.querySelector('.local-contexts-dialog__trigger');
        const dialog = wrapper.querySelector('dialog');

        if (!trigger || !dialog) {
          return;
        }

        const closeBtn = dialog.querySelector('.local-contexts-dialog__close');

        trigger.addEventListener('click', () => {
          dialog.showModal();
          trigger.setAttribute('aria-expanded', 'true');
          if (closeBtn) {
            closeBtn.focus({ preventScroll: true });
          }
        });

        if (closeBtn) {
          closeBtn.addEventListener('click', () => {
            dialog.close();
          });
        }

        dialog.addEventListener('click', (e) => {
          if (e.target === dialog) {
            dialog.close();
          }
        });

        dialog.addEventListener('close', () => {
          trigger.setAttribute('aria-expanded', 'false');
          trigger.focus();
        });
      });
    },
  };
})(Drupal, once);
