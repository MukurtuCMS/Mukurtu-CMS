/**
 * @file
 * Strips table semantics from Drupal's generic multi-value field table
 * (e.g. External Links) on the public submission form. That markup is
 * styled as plain flex rows here (see _submissions.scss's
 * .field-multiple-table rules), not an actual grid of data, so a screen
 * reader announcing "table", row/column counts, etc. would be describing
 * a structure that no longer visually exists.
 */
(function (Drupal, once) {
  Drupal.behaviors.mukurtuSubmissionsFlattenTables = {
    attach(context) {
      const tables = once(
        'mukurtu-submissions-flatten-tables',
        '.mukurtu-submissions-public-submission-form .field-multiple-table',
        context,
      );

      tables.forEach((table) => {
        table.setAttribute('role', 'presentation');
        table
          .querySelectorAll('thead, tbody, tr, th, td')
          .forEach((el) => el.setAttribute('role', 'none'));
      });
    },
  };
})(Drupal, once);
