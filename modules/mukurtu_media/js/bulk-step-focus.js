(function (Drupal, once) {

  'use strict';

  /**
   * Moves focus to the Step 2 heading after a form step transition.
   *
   * Both bulk upload and bulk URL create forms rebuild server-side; the page
   * reloads entirely. Moving focus to the step heading ensures keyboard and
   * screen reader users are oriented at the start of the new step content
   * rather than having focus dropped to <body> (WCAG 2.4.3 Focus Order).
   *
   * The heading uses tabindex="-1" so it can receive programmatic focus
   * without entering the natural tab order (WCAG anti-pattern: tabindex > 0).
   */
  Drupal.behaviors.mukurtuBulkStepFocus = {
    attach: function (context) {
      once('bulk-step-focus', '#bulk-upload-step-heading', context).forEach(function (heading) {
        heading.focus();
      });
    }
  };

}(Drupal, once));
