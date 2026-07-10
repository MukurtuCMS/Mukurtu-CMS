(function (Drupal) {
  // jQuery UI Dialog does not set aria-modal="true", which allows VoiceOver's
  // virtual cursor to navigate behind the modal. Fix this on every dialog open.
  Drupal.behaviors.mukurtuDialogAriaModal = {
    attach: function (context, settings) {
      $(document).on('dialogopen.mukurtuAriaModal', function (event) {
        $(event.target).closest('.ui-dialog').attr('aria-modal', 'true');
      });
    },
  };
})(Drupal);
