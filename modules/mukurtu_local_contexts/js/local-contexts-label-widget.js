((Drupal, once) => {
  "use strict";

  Drupal.behaviors.localContextsLabelWidget = {
    attach(context) {
      once('local-contexts-label-widget', '.field--name-field-local-contexts-projects', context).forEach((projectsWrapper) => {
        const form = projectsWrapper.closest('form') || document;
        const labelsWrapper = form.querySelector('.field--name-field-local-contexts-labels-and-notices');

        if (!labelsWrapper) {
          return;
        }

        const status = document.createElement('div');
        status.className = 'visually-hidden';
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        labelsWrapper.insertAdjacentElement('afterbegin', status);

        const syncState = (checkbox) => {
          const projectId = checkbox.getAttribute('data-project-id');
          const group = labelsWrapper.querySelector(`details[data-project-id="${CSS.escape(projectId)}"]`);

          if (!group) {
            return;
          }

          const groupCheckboxes = group.querySelectorAll('input[type="checkbox"]');
          const projectTitle = group.querySelector('summary')?.textContent.trim() ?? '';

          group.classList.toggle('is-disabled', checkbox.checked);
          groupCheckboxes.forEach((groupCheckbox) => {
            groupCheckbox.disabled = checkbox.checked;
            if (checkbox.checked) {
              groupCheckbox.checked = false;
            }
          });

          status.textContent = checkbox.checked
            ? Drupal.t('Label and notice options for @project are unavailable because the entire project is already selected.', { '@project': projectTitle })
            : Drupal.t('Label and notice options for @project are available again.', { '@project': projectTitle });
        };

        // Only sync in response to the user actively checking/unchecking a
        // project during this session — not on initial attach. Running this
        // on attach would disable (and thus silently exclude from the next
        // submission) any label checkboxes already saved on existing content
        // whose project happens to already be checked, discarding that data
        // the moment the form is saved for an unrelated reason.
        projectsWrapper.querySelectorAll('input[type="checkbox"][data-project-id]').forEach((checkbox) => {
          checkbox.addEventListener('change', () => syncState(checkbox));
        });
      });
    },
  };
})(Drupal, once);
