<?php

namespace Drupal\mukurtu_local_contexts\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mukurtu_local_contexts\LocalContextsApi;
use Drupal\mukurtu_local_contexts\LocalContextsProject;
use Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Local Contexts form that can be used by both groups and sites.
 */
abstract class ManageSupportedProjectsBase extends FormBase {

  /**
   * Constructs the form with dependencies.
   *
   * @param \Drupal\mukurtu_local_contexts\LocalContextsSupportedProjectManager $supportedProjectManager
   *   The Local Contexts supported project manager.
   */
  public function __construct(protected LocalContextsSupportedProjectManager $supportedProjectManager) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('mukurtu_local_contexts.supported_project_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $group = $form_state->get('group');

    // Get the list of API keys currently configured for this scope.
    if ($group) {
      $api_keys = $this->supportedProjectManager->getGroupApiKeys($group);
    }
    else {
      $api_keys = $this->supportedProjectManager->getSiteApiKeys();
    }

    // Fetch and merge the projects visible to each configured API key.
    $all_projects = [];
    foreach ($api_keys as $api_key) {
      $lcApi = new LocalContextsApi();
      $key_projects_response = $lcApi->makeMultipageRequest('/projects', $api_key);
      if (is_array($key_projects_response) && $lcApi->getErrorMessage() == '') {
        foreach ($key_projects_response as $project_response) {
          $id = $project_response['unique_id'];
          if (!isset($all_projects[$id])) {
            $project_response['_api_key'] = $api_key;
            $all_projects[$id] = $project_response;
          }
        }
      }
      else {
        $this->messenger()->addError(t('Could not retrieve Local Contexts project information for the API key <code>@key</code>. Requesting the project list returned the following error: <code>@error</code>', [
          '@key' => $this->maskApiKey($api_key),
          '@error' => $lcApi->getErrorMessage(),
        ]));
      }
    }
    $form_state->setTemporaryValue('all_projects', $all_projects);

    // Get the list of supported projects for this group or the entire site.
    if ($group) {
      $supported_projects = $this->supportedProjectManager->getGroupSupportedProjects($group);
    }
    else {
      $supported_projects = $this->supportedProjectManager->getSiteSupportedProjects();
    }
    $form_state->setTemporaryValue('supported_projects', $supported_projects);

    // Get the admin-provided labels for this scope's API keys, since the
    // Hub only shows the account name a key belongs to on its own site.
    $key_labels = $group
      ? $this->supportedProjectManager->getGroupApiKeyLabels($group)
      : $this->supportedProjectManager->getSiteApiKeyLabels();

    $form['#tree'] = TRUE;
    $form['#attached']['library'][] = 'mukurtu_local_contexts/manage-projects';

    $form['api_key_wrapper'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API Keys'),
      '#description' => $this->t('You can find these on the community settings page on the Local Contexts Hub. Add one key per Local Contexts Hub account you want to connect.'),
    ];

    if ($api_keys) {
      $form['api_key_wrapper']['keys'] = [
        '#type' => 'table',
        '#header' => [
          ['data' => $this->t('API Key'), 'scope' => 'col'],
          ['data' => $this->t('Operations'), 'scope' => 'col'],
        ],
      ];
      foreach ($api_keys as $delta => $api_key) {
        $form['api_key_wrapper']['keys'][$delta]['value'] = [
          '#type' => 'markup',
          '#markup' => '<span class="api-key-current">' . $this->formatApiKeyDisplay($api_key, $key_labels) . '</span>',
        ];
        $form['api_key_wrapper']['keys'][$delta]['remove'] = [
          '#type' => 'submit',
          '#name' => 'remove_api_key_' . $delta,
          '#value' => $this->t('Remove'),
          '#api_key' => $api_key,
          '#attributes' => [
            'aria-label' => $this->t('Remove API key @key', ['@key' => $this->maskApiKey($api_key)]),
          ],
          '#validate' => [],
          '#submit' => ['::removeApiKey'],
          '#limit_validation_errors' => [],
        ];
      }
    }

    $form['api_key_wrapper']['add'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['api_key_wrapper']['add']['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add API key'),
      '#required' => TRUE,
      '#parents' => ['api_key'],
    ];
    $form['api_key_wrapper']['add']['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Name (optional)'),
      '#maxlength' => 255,
      '#parents' => ['api_key_label'],
    ];
    $form['api_key_wrapper']['add']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add key'),
      '#validate' => ['::validateApiKey'],
      '#submit' => ['::submitApiKey'],
      // Restrict validation to this button's own fields, since the API key
      // field is required but other buttons on this form (bulk actions,
      // per-key removal) shouldn't be blocked by it being empty.
      '#limit_validation_errors' => [['api_key'], ['api_key_label']],
    ];

    $form['bulk_action_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['bulk-action-wrapper', 'container-inline']],
      '#tree' => FALSE,
    ];
    $form['bulk_action_wrapper']['action'] = [
      '#type' => 'select',
      '#title' => $this->t('With selected items'),
      '#options' => [
        'add' => $this->t('Add / Sync'),
        'delete' => $this->t('Delete'),
      ],
      '#empty_option' => $this->t('- Select action -'),
    ];
    $form['bulk_action_wrapper']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply action'),
      '#button_type' => 'primary',
      // '#disabled' is finalized below once it's known whether there's
      // anything selectable, including already-tracked projects that are no
      // longer visible from the Hub (e.g. all keys were removed) and only
      // need deleting, not a live Hub key.
      '#disabled' => TRUE,
      '#validate' => ['::validateForm'],
      '#submit' => ['::submitForm'],
      // Restrict validation to this button's own fields, since the API key
      // field being required (for the separate "Add key" button) shouldn't
      // block bulk actions on the existing project list.
      '#limit_validation_errors' => [['action'], ['projects']],
    ];

    // Set in child classes.
    $form['projects_caption'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => '',
    ];

    $project_header = [
      'title' => $this->t('Title'),
      'status' => $this->t('Status'),
      'last_sync' => $this->t('Last synced'),
      'project_id' => $this->t('Project ID'),
    ];

    // Group every project's row by the API key it belongs to, so each key's
    // projects render under their own heading instead of a per-row Source
    // column repeating the same (often long) key on every line.
    $rows_by_key = [];
    foreach ($all_projects as $project) {
      $id = $project['unique_id'];
      $row = [
        'title' => $project['title'],
        'status' => $this->t('Not added'),
        'last_sync' => '',
        'project_id' => $id,
      ];
      if (isset($supported_projects[$id])) {
        $row['last_sync'] = \Drupal::service('date.formatter')->format(
          $supported_projects[$id]['updated'],
          'short'
        );
        $row['status'] = $this->t('Active');
      }
      $rows_by_key[$project['_api_key']][$id] = $row;
    }

    // Loop over any remaining projects that are no longer on the hub.
    $missing_projects = array_diff_key($supported_projects, $all_projects);
    foreach ($missing_projects as $id => $project) {
      $row = [
        'title' => $project['title'],
        'status' => [
          'data' => [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $this->t('Not available'),
            '#attributes' => ['title' => $this->t('This project has been deleted from the Local Contexts Hub and can no longer be synced.')],
          ],
        ],
        'project_id' => $id,
      ];
      // Group with its originating key if that key is still configured;
      // otherwise it falls into the "No API key" section below alongside
      // legacy/NULL-api_key projects.
      $key = $project['api_key'] ?? '';
      $rows_by_key[in_array($key, $api_keys, TRUE) ? $key : ''][$id] = $row;
    }

    $form['projects'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $has_projects = FALSE;
    foreach ($api_keys as $delta => $api_key) {
      if (empty($rows_by_key[$api_key])) {
        continue;
      }
      $has_projects = TRUE;
      $form['projects'][$delta]['table'] = [
        '#type' => 'tableselect',
        '#header' => $project_header,
        '#options' => $rows_by_key[$api_key],
        // Using the table's own caption (rather than a preceding heading)
        // keeps each section's label programmatically tied to its table,
        // including for the "select all" checkbox screen readers announce.
        '#caption' => $this->formatApiKeyDisplay($api_key, $key_labels),
        '#attributes' => ['class' => ['mukurtu-local-contexts-project-table']],
        '#js_select' => TRUE,
      ];
      unset($rows_by_key[$api_key]);
    }

    // Anything left over isn't attributable to a currently configured key.
    if (!empty($rows_by_key[''])) {
      $has_projects = TRUE;
      $form['projects']['unattributed']['table'] = [
        '#type' => 'tableselect',
        '#header' => $project_header,
        '#options' => $rows_by_key[''],
        '#caption' => $this->t('No API key'),
        '#attributes' => ['class' => ['mukurtu-local-contexts-project-table']],
        '#js_select' => TRUE,
      ];
    }

    if (!$has_projects) {
      $form['projects']['empty'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('No Local Context projects are available to the configured API keys. Add an API key above, and check that projects have been set up within that Local Contexts Hub account.'),
      ];
    }

    $form['bulk_action_wrapper']['submit']['#disabled'] = !$has_projects;

    return $form;
  }

  /**
   * Mask an API key for display, showing only its first 10 characters.
   *
   * @param string $api_key
   *   The API key.
   *
   * @return string
   *   The masked API key.
   */
  protected function maskApiKey(string $api_key): string {
    return substr($api_key, 0, 10) . str_repeat('X', max(0, strlen($api_key) - 10));
  }

  /**
   * Format an API key for display, using its admin-provided label if set.
   *
   * @param string $api_key
   *   The API key.
   * @param string[] $key_labels
   *   Labels keyed by API key, as returned by getSiteApiKeyLabels() or
   *   getGroupApiKeyLabels().
   *
   * @return string
   *   The formatted display value.
   */
  protected function formatApiKeyDisplay(string $api_key, array $key_labels): string {
    $label = $key_labels[$api_key] ?? NULL;
    return $label
      ? $this->t('@label (@key)', ['@label' => $label, '@key' => $this->maskApiKey($api_key)])
      : $this->maskApiKey($api_key);
  }

  /**
   * {@inheritdoc}
   */
  public function validateApiKey(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $lcApi = new LocalContextsApi();
    $valid_key = $lcApi->validateApiKey($api_key);
    if (!$valid_key) {
      $error_message = $lcApi->getErrorMessage();
      $form_state->setErrorByName('api_key', $error_message);
    }
  }

  /**
   * Submit handler for the "Add key" button that adds a new API key.
   */
  public function submitApiKey(array &$form, FormStateInterface $form_state) {
    $api_key = $form_state->getValue('api_key');
    $label = trim((string) $form_state->getValue('api_key_label'));
    $group = $form_state->get('group');

    if ($group) {
      $keys = $this->supportedProjectManager->getGroupApiKeys($group);
      if (!in_array($api_key, $keys, TRUE)) {
        $keys[] = $api_key;
        $group->set('field_local_contexts_api_key', $keys);
        $group->save();
      }
      if ($label !== '') {
        $this->supportedProjectManager->setGroupApiKeyLabel($group, $api_key, $label);
      }
    }
    else {
      $keys = $this->supportedProjectManager->getSiteApiKeys();
      if (!in_array($api_key, $keys, TRUE)) {
        $keys[] = $api_key;
        $this->configFactory()->getEditable('mukurtu_local_contexts.settings')->set('site_api_keys', $keys)->save();
      }
      if ($label !== '') {
        $this->supportedProjectManager->setSiteApiKeyLabel($api_key, $label);
      }
    }

    // Clear the submitted values so the fields are empty on the rebuilt
    // form, rather than showing the just-added key back to the user.
    // Textfield's valueCallback() repopulates from the raw user input on
    // rebuild, not from $form_state's processed values, so both must be
    // cleared.
    $form_state->setValueForElement($form['api_key_wrapper']['add']['api_key'], '');
    $form_state->setValueForElement($form['api_key_wrapper']['add']['label'], '');
    $user_input = $form_state->getUserInput();
    NestedArray::setValue($user_input, $form['api_key_wrapper']['add']['api_key']['#parents'], '');
    NestedArray::setValue($user_input, $form['api_key_wrapper']['add']['label']['#parents'], '');
    $form_state->setUserInput($user_input);
    $form_state->setRebuild();
  }

  /**
   * Submit handler for a "Remove" button that removes a configured API key.
   */
  public function removeApiKey(array &$form, FormStateInterface $form_state) {
    $trigger = $form_state->getTriggeringElement();
    $api_key = $trigger['#api_key'] ?? NULL;
    if (empty($api_key)) {
      return;
    }
    $group = $form_state->get('group');

    $in_use = $group
      ? $this->supportedProjectManager->getGroupProjectsByApiKey($group, $api_key)
      : $this->supportedProjectManager->getSiteProjectsByApiKey($api_key);

    // Projects with no recorded API key (added before per-project key
    // tracking existed, or legacy migrated projects) can't be attributed
    // to a specific key. If any of them are still in use, block removal
    // of any key in this scope rather than letting them go untracked.
    $unattributed = $group
      ? $this->supportedProjectManager->getGroupProjectsWithoutApiKey($group)
      : $this->supportedProjectManager->getSiteProjectsWithoutApiKey();
    foreach ($unattributed as $id) {
      if ((new LocalContextsProject($id))->inUse()) {
        $in_use[] = $id;
      }
    }

    if ($in_use) {
      $this->messenger()->addError($this->formatPlural(
        count($in_use),
        'This API key cannot be removed because @count project added with it is still supported. Remove those projects first.',
        'This API key cannot be removed because @count projects added with it are still supported. Remove those projects first.'
      ));
      return;
    }

    if ($group) {
      $keys = array_values(array_diff($this->supportedProjectManager->getGroupApiKeys($group), [$api_key]));
      $group->set('field_local_contexts_api_key', $keys);
      $group->save();
      $this->supportedProjectManager->removeGroupApiKeyLabel($group, $api_key);
    }
    else {
      $keys = array_values(array_diff($this->supportedProjectManager->getSiteApiKeys(), [$api_key]));
      $this->configFactory()->getEditable('mukurtu_local_contexts.settings')->set('site_api_keys', $keys)->save();
      $this->supportedProjectManager->removeSiteApiKeyLabel($api_key);
    }

    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Projects render as one tableselect per API key section, so their
    // selections need to be merged back into a single flat list.
    $selected_projects = [];
    foreach ((array) $form_state->getValue('projects') as $section) {
      if (isset($section['table']) && is_array($section['table'])) {
        $selected_projects += $section['table'];
      }
    }
    $selected_projects = array_filter($selected_projects);
    /** @var ContentEntityInterface $group */
    $group = $form_state->get('group');
    $action = $form_state->getValue('action');
    $all_projects = (array) $form_state->getTemporaryValue('all_projects');
    $supported_projects = (array) $form_state->getTemporaryValue('supported_projects');

    // If no items are selected, throw an error.
    if (!$selected_projects) {
      $this->messenger()->addError($this->t('Select at least one project to modify.'));
      return;
    }

    switch ($action) {
      case 'add':
        $this->submitAdd($all_projects, $selected_projects, $group, $supported_projects);
        break;
      case 'delete':
        $this->submitDelete($all_projects, $selected_projects, $group);
        break;
      default:
        $form_state->setErrorByName('action', $this->t('Select an action to apply.'));
    }
  }

  /**
   * Add projects to the site or group.
   *
   * @param array $all_projects
   *   All projects to which the Local Contexts API key has access.
   * @param array $selected_projects
   *   An array of IDs that were selected to be added.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $group
   *   If adding to a group, the entity object. If NULL, adding to the site-wide
   *   projects.
   * @param array $supported_projects
   *   The projects already supported at this scope, keyed by project ID.
   *
   * @return void
   */
  protected function submitAdd(array $all_projects, array $selected_projects, ?ContentEntityInterface $group, array $supported_projects): void {
    $added_count = 0;
    $sync_count = 0;
    $last_added_title = '';
    $last_synced_title = '';
    $failed_titles = [];
    $selected_projects = array_filter($selected_projects);
    foreach ($selected_projects as $id) {
      // Prefer the API key this project was originally added with, since
      // re-syncing shouldn't change which account it's associated with.
      $known_api_key = $supported_projects[$id]['api_key'] ?? $all_projects[$id]['_api_key'] ?? NULL;

      $project = new LocalContextsProject($id);
      // If never updated before, this is a new project.
      $is_new = !$project->getUpdated();

      if ($known_api_key !== NULL) {
        $candidate_keys = [$known_api_key];
      }
      else {
        // The project list built for this request didn't include this
        // project (this form can be rebuilt more than once per submission,
        // and the cached project list isn't guaranteed to reflect the
        // latest one). Fall back to trying every key currently configured
        // for this scope rather than sending a request with no API key,
        // which the Hub will always reject.
        $candidate_keys = $group
          ? $this->supportedProjectManager->getGroupApiKeys($group)
          : $this->supportedProjectManager->getSiteApiKeys();
      }

      $api_key = NULL;
      foreach ($candidate_keys as $candidate_key) {
        if ($project->fetchFromHub($candidate_key)) {
          $api_key = $candidate_key;
          break;
        }
      }

      if ($api_key === NULL) {
        // Don't track this project if the hub fetch failed, otherwise it
        // ends up recorded as supported without a matching local copy.
        $failed_titles[] = $all_projects[$id]['title'] ?? $id;
        continue;
      }
      if ($group) {
        $this->supportedProjectManager->addGroupProject($group, $id, $api_key);
      }
      else {
        $this->supportedProjectManager->addSiteProject($id, $api_key);
      }
      if ($is_new) {
        $added_count++;
        $last_added_title = $project->getTitle();
      }
      else {
        $sync_count++;
        $last_synced_title = $project->getTitle();
      }
    }

    // Projects can be both added and synced in the same request.
    if ($added_count) {
      $message = $this->formatPlural($added_count, 'The project @title has been added.', '@count projects have been added.', [
        '@title' => $last_added_title,
      ]);
      $this->messenger()->addStatus($message);
    }
    if ($sync_count) {
      $message = $this->formatPlural($sync_count, 'The project @title has been synced.', '@count projects have been synced.', [
        '@title' => $last_synced_title,
      ]);
      $this->messenger()->addStatus($message);
    }
    if ($failed_titles) {
      $this->messenger()->addError($this->t('The following project(s) could not be added or synced because the Local Contexts Hub request failed: %titles. Try again.', [
        '%titles' => implode(', ', $failed_titles),
      ]));
    }
  }

  /**
   * Remove projects from the site or group.
   *
   * @param array $all_projects
   *   All projects to which the Local Contexts API key has access.
   * @param array $selected_projects
   *   An array of IDs that were selected to be added.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $group
   *   If removing from a group, the entity object. If NULL, removing from the
   *   site-wide projects.
   *
   * @return void
   */
  protected function submitDelete(array $all_projects, array $selected_projects, ?ContentEntityInterface $group): void {
    foreach ($selected_projects as $id) {
      if ($projectToRemove = new LocalContextsProject($id)) {
        // Ensure this project is added before trying to remove it.
        $is_group_project = $group && $this->supportedProjectManager->isGroupSupportedProject($group, $id);
        $is_site_project = !$group && $this->supportedProjectManager->isSiteSupportedProject($id);
        if (!$is_group_project && !$is_site_project) {
          $title = $all_projects[$id]['title'] ?? '';
          $this->messenger()->addWarning($this->t('The project %project was not added, so no delete action was taken on it.', ['%project' => $title]));
          continue;
        }

        if (!$projectToRemove->inUse()) {
          if ($group) {
            $this->supportedProjectManager->removeGroupProject($group, $id);
          }
          else {
            $this->supportedProjectManager->removeSiteProject($id);
          }
          $title = $projectToRemove->getTitle();
          $this->messenger()->addStatus($this->t('Removed project %project.', ['%project' => $title]));
        }
        else {
          $title = $projectToRemove->getTitle();
          $this->messenger()->addError($this->t('The project %project cannot be removed because it is in use. Remove any uses of this project before deleting.', ['%project' => $title]));
        }
      }
    }
  }

}
