/**
 * @file
 * Blocks clicks on "use-ajax" dialog links until Drupal's own AJAX/dialog
 * JS has attached.
 *
 * These links are plain <a href> tags that only open as a modal once core's
 * ajax.js has bound a click handler to them via Drupal.attachBehaviors(). If
 * a user clicks before that has happened (page still loading), the browser
 * just follows the href and lands on the modal route as a full page instead.
 * This has no dependencies and must load inline in <head> (see
 * mukurtu_core_page_attachments()) so the guard is in place before any body
 * content is interactive. See modal-click-guard-ready.js for the
 * counterpart that lifts the guard once real JS is ready.
 *
 * Uses a data attribute rather than a class on <html>: theme/vendor scripts
 * (e.g. Modernizr-style feature detection) commonly overwrite
 * documentElement.className wholesale rather than adding to it, which would
 * silently wipe out a class-based flag added earlier in <head>.
 *
 * A blocked click isn't just dropped: once the guard lifts, it's replayed
 * on the same link, so the user's click still opens the modal (a moment
 * late) instead of silently doing nothing - important since the only other
 * feedback while blocked (a wait cursor, see modal-click-guard.css) isn't
 * available to touch, keyboard, or screen reader users.
 */
(function () {
  var root = document.documentElement;
  var attr = 'data-mukurtu-modal-guard';
  root.setAttribute(attr, 'pending');

  // Rare, but possible: the user clicks more than one modal link before the
  // guard lifts. Track all of them so none is left stuck aria-disabled
  // forever, but only replay the most recent one - it's the best available
  // signal of final intent, and replaying every blocked click would risk
  // stacking multiple modals on top of each other.
  var blockedLinks = [];

  document.addEventListener('click', function (event) {
    if (root.getAttribute(attr) !== 'pending') {
      return;
    }
    var trigger = event.target.closest('.use-ajax[data-dialog-type]');
    if (trigger) {
      event.preventDefault();
      if (blockedLinks.indexOf(trigger) === -1) {
        blockedLinks.push(trigger);
      }
      trigger.setAttribute('aria-disabled', 'true');
    }
  }, true);

  // The guard attribute only ever transitions pending -> removed once in a
  // page's lifetime, so this observer has nothing left to do after its
  // first callback and disconnects itself there.
  var observer = new MutationObserver(function () {
    observer.disconnect();
    var linkToReplay = blockedLinks[blockedLinks.length - 1];
    blockedLinks.forEach(function (link) {
      link.removeAttribute('aria-disabled');
    });
    if (linkToReplay) {
      linkToReplay.click();
    }
  });
  observer.observe(root, { attributes: true, attributeFilter: [attr] });

  // Safety net: if Drupal's behaviors never attach (blocked or broken JS
  // elsewhere on the page), don't leave these links permanently unusable.
  window.setTimeout(function () {
    root.removeAttribute(attr);
  }, 5000);
})();
