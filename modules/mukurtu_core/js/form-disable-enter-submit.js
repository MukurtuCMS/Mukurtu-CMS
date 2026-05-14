(function (Drupal, once) {
  // Input types where Enter would otherwise trigger form submission.
  const TEXT_INPUT_TYPES = new Set([
    'text', 'email', 'url', 'number', 'tel', 'password',
    'date', 'datetime-local', 'month', 'week', 'time', 'color',
  ]);

  function isExemptForm(form) {
    // Search forms and the login form: Enter-to-submit is expected behavior.
    if (form.querySelector('input[type="search"]')) return true;
    if (form.getAttribute('role') === 'search') return true;
    if (form.id === 'user-login-form') return true;
    // Single visible text input: Enter-to-submit is the expected keyboard interaction.
    const visibleTextInputs = form.querySelectorAll(
      'input:not([type="hidden"]):not([type="submit"]):not([type="button"]):not([type="reset"]):not([type="image"])'
    );
    if (visibleTextInputs.length === 1) return true;
    return false;
  }

  function handleKeydown(event) {
    if (event.key !== 'Enter') return;
    const input = event.target;
    if (!input.matches('input')) return;
    if (!TEXT_INPUT_TYPES.has((input.type || 'text').toLowerCase())) return;
    const form = input.closest('form');
    if (!form || isExemptForm(form)) return;
    event.preventDefault();
  }

  Drupal.behaviors.mukurtuFormDisableEnterSubmit = {
    attach(context) {
      once('mukurtu-disable-enter-submit', 'form', context).forEach((form) => {
        form.addEventListener('keydown', handleKeydown);
      });
    },
  };
})(Drupal, once);
