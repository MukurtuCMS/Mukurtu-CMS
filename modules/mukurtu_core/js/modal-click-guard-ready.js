/**
 * @file
 * Lifts the click guard from modal-click-guard.js once Drupal's own
 * behaviors have attached to the document.
 *
 * Drupal.attachBehaviors(document) runs synchronously, so by the time this
 * runs, core ajax.js's click handler has already been bound in the same
 * tick - closing the race window this pair of scripts exists to guard.
 */
(function (Drupal) {
  Drupal.behaviors.mukurtuModalClickGuardReady = {
    attach(context) {
      if (context === document) {
        document.documentElement.removeAttribute('data-mukurtu-modal-guard');
      }
    },
  };
})(Drupal);
