/**
 * @file
 * Live character-remaining counters for length-limited fields on the
 * public submission form.
 */
(function (Drupal, once) {
  Drupal.behaviors.mukurtuSubmissionsCharacterCounter = {
    attach(context) {
      const fields = once(
        'mukurtu-submissions-character-counter',
        '.mukurtu-submissions-public-submission-form input[maxlength], .mukurtu-submissions-public-submission-form textarea[maxlength]',
        context,
      );

      fields.forEach((field) => {
        const max = parseInt(field.getAttribute('maxlength'), 10);
        if (!max) {
          return;
        }

        const counter = document.createElement('div');
        counter.className = 'submission-character-counter';
        field.insertAdjacentElement('afterend', counter);

        const update = () => {
          const remaining = max - field.value.length;
          counter.textContent = Drupal.formatPlural(
            remaining,
            '1 character remaining',
            '@count characters remaining',
          );
        };

        field.addEventListener('input', update);
        update();
      });
    },
  };
})(Drupal, once);
